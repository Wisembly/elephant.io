<?php
/**
 * This file is part of the Elephant.io package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

namespace ElephantIO\Engine\SocketIO;

use DomainException,
    InvalidArgumentException,
    UnexpectedValueException;

use Psr\Log\LoggerInterface;

use ElephantIO\EngineInterface,
    ElephantIO\Engine\AbstractSocketIO,

    ElephantIO\Payload\Encoder,
    ElephantIO\Payload\Decoder,

    ElephantIO\Exception\SocketException,
    ElephantIO\Exception\UnsupportedTransportException;

/**
 * Implements the dialog with Socket.IO version 1.x
 *
 * Based on the work of Mathieu Lallemand (@lalmat)
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 * @link https://tools.ietf.org/html/rfc6455#section-5.2 Websocket's RFC
 */
class Version1X extends AbstractSocketIO
{
    const TRANSPORT_POLLING   = 'polling';
    const TRANSPORT_WEBSOCKET = 'websocket';

    /** @var resource Resource to the connected stream */
    protected $stream;

    /** {@inheritDoc} */
    public function connect()
    {
        if (is_resource($this->stream)) {
            return;
        }

        $this->handshake();

        $errors = [null, null];
        $host   = $this->url['host'];

        if (true === $this->url['secured']) {
            $host = 'ssl://' . $host;
        }

        $this->stream = fsockopen($host, $this->url['port'], $errors[0], $errors[1], $this->options['timeout']);

        if (!is_resource($this->stream)) {
            throw new SocketException($error[0], $error[1]);
        }

        $this->upgradeTransport();
    }

    /** {@inheritDoc} */
    public function close()
    {
        if (!is_resource($this->stream)) {
            return;
        }

        $this->write(EngineInterface::CLOSE);

        fclose($this->stream);
        $this->stream = null;
    }

    /** {@inheritDoc} */
    public function emit($event, array $args)
    {
        $this->write(EngineInterface::MESSAGE, static::EVENT . json_encode([$event, $args]));
    }

    /** {@inheritDoc} */
    public function write($code, $message = null)
    {
        if (!is_resource($this->stream)) {
            return;
        }

        if (!is_int($code) || 0 > $code || 6 < $code) {
            throw new InvalidArgumentException('Wrong message type when trying to write on the socket');
        }

        $payload = new Encoder($code . $message, Encoder::OPCODE_TEXT, true);
        return fwrite($this->stream, (string) $payload);
    }

    /**
     * {@inheritDoc}
     *
     * Be careful, this method may hang your script, as we're not in a non
     * blocking mode.
     */
    public function read()
    {
        /*
         * The first byte contains the FIN bit, the reserved bits, and the
         * opcode... We're not interested in them. Yet.
         */
        $data = fread($this->stream, 1);

        // the second byte contains the mask bit and the payload's length
        $data  .= $part = fread($this->stream, 1);
        $length = (int)  ($part & ~0b10000000); // removing the mask bit
        $mask   = (bool) ($part &  0b10000000);

        /*
         * Here is where it is getting tricky :
         *
         * - If the length <= 125, then we do not need to do anything ;
         * - if the length is 126, it means that it is coded over the next 2 bytes ;
         * - if the length is 127, it means that it is coded over the next 8 bytes.
         *
         * But,here's the trick : we cannot interpret a length over 127 if the
         * system does not support 64bits integers (such as Windows, or 32bits
         * processors architectures).
         */
        switch ($length) {
            case 0b1111101: // 125
            break;

            case 0b1111110: // 126
                $length = unpack('n', fread($this->stream, 2));
            break;

            case 0b1111111: // 127
                // are (at least) 64 bits not supported by the architecture ?
                if (8 > PHP_INT_SIZE) {
                    throw new DomainException('64 bits unsigned integer are not supported on this architecture');
                }

                /*
                 * As (un)pack does not support unpacking 64bits unsigned
                 * integer, we need to split the data
                 *
                 * {@link http://stackoverflow.com/questions/14405751/pack-and-unpack-64-bit-integer}
                 */
                list($left, $right) = array_values(unpack('N2', fread($this->stream, 8)));
                $length = $left << 32 | $right;
            break;
        }

        // incorporate the mask key if the mask bit is 1
        if (true === $mask) {
            $data .= fread($this->stream, 4);
        }

        // decode the payload
        return (string) new Decoder(fread($this->stream, $length));
    }

    /** {@inheritDoc} */
    public function getName()
    {
        return 'SocketIO Version 1.X';
    }

    /** {@inheritDoc} */
    protected function getDefaultOptions()
    {
        $defaults = parent::getDefaultOptions();

        $defaults['version']   = 2;
        $defaults['use_b64']   = false;
        $defaults['transport'] = static::TRANSPORT_POLLING;

        return $defaults;
    }

    /** Does the handshake with the Socket.io server and populates the `session` value object */
    protected function handshake()
    {
        if (null !== $this->session) {
            return;
        }

        $query = ['use_b64'   => $this->options['use_b64'],
                  'EIO'       => $this->options['version'],
                  'transport' => $this->options['transport']];

        if (isset($this->url['query'])) {
            $query = array_replace($query, $this->url['query']);
        }

        $url = sprintf('%s://%s:%d/%s/?%s', true === $this->url['secured'] ? 'ssl' : $this->url['scheme'], $this->url['host'], $this->url['port'], trim($this->url['path'], '/'), http_build_query($query));

        $result  = file_get_contents($url);
        $decoded = json_decode(substr($result, strpos($result, '{')), true);

        if (!in_array('websocket', $decoded['upgrades'])) {
            throw new UnsupportedTransportException('websocket');
        }

        $this->session = new Session($decoded['sid'], $decoded['pingInterval'], $decoded['pingTimeout'], $decoded['upgrades']);
    }

    /** Upgrades the transport to WebSocket */
    private function upgradeTransport()
    {
        $query = ['sid'       => $this->session->id,
                  'EIO'       => $this->options['version'],
                  'use_b64'   => $this->options['use_b64'],
                  'transport' => static::TRANSPORT_WEBSOCKET];

        $url = sprintf('/%s/?%s', trim($this->url['path'], '/'), http_build_query($query));
        $key = base64_encode(sha1(uniqid(mt_rand(), true), true));

        $request = "GET {$url} HTTP/1.1\r\n"
                 . "Host: {$this->url['host']}\r\n"
                 . "Upgrade: WebSocket\r\n"
                 . "Connection: Upgrade\r\n"
                 . "Sec-WebSocket-Key: {$key}\r\n"
                 . "Sec-WebSocket-Version: 13\r\n"
                 . "Origin: *\r\n\r\n";

        fwrite($this->stream, $request);
        $result = fread($this->stream, 12);

        if ('HTTP/1.1 101' !== $result) {
            throw new UnexpectedValueException(sprintf('The server returned an unexpected value. Expected "HTTP/1.1 101", had "%s"', $result));
        }

        // cleaning up the stream
        while ('' !== trim(fgets($this->stream)));

        $this->write(EngineInterface::UPGRADE);
    }
}

