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
    ElephantIO\Payload\Encoder,
    ElephantIO\Engine\AbstractSocketIO,

    ElephantIO\Exception\SocketException,
    ElephantIO\Exception\UnsupportedTransportException,
    ElephantIO\Exception\ServerConnectionFailureException;

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

    /** {@inheritDoc} */
    public function connect()
    {
        if (is_resource($this->stream)) {
            return;
        }

        $this->handshake();

        $errors = [null, null];
        $host   = sprintf('%s:%d', $this->url['host'], $this->url['port']);

        if (true === $this->url['secured']) {
            $host = 'ssl://' . $host;
        }

        $this->stream = stream_socket_client($host, $errors[0], $errors[1], $this->options['timeout'], STREAM_CLIENT_CONNECT, stream_context_create($this->options['context']));

        if (!is_resource($this->stream)) {
            throw new SocketException($error[0], $error[1]);
        }

        stream_set_timeout($this->stream, $this->options['timeout']);

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
        return $this->write(EngineInterface::MESSAGE, static::EVENT . json_encode([$event, $args]));
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
        $bytes = fwrite($this->stream, (string) $payload);

        // wait a little bit of time after this message was sent
        usleep((int) $this->options['wait']);

        return $bytes;
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

        $url    = sprintf('%s://%s:%d/%s/?%s', $this->url['scheme'], $this->url['host'], $this->url['port'], trim($this->url['path'], '/'), http_build_query($query));
        $result = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => (float) $this->options['timeout']]]));

        if (false === $result) {
            throw new ServerConnectionFailureException;
        }

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

