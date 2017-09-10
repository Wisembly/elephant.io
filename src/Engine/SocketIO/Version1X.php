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

use DomainException;
use InvalidArgumentException;
use UnexpectedValueException;

use Psr\Log\LoggerInterface;

use ElephantIO\EngineInterface;
use ElephantIO\Payload\Encoder;
use ElephantIO\Engine\AbstractSocketIO;

use ElephantIO\Exception\SocketException;
use ElephantIO\Exception\UnsupportedTransportException;
use ElephantIO\Exception\ServerConnectionFailureException;

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

        $protocol = 'http';
        $errors = [null, null];
        $host   = sprintf('%s:%d', $this->url['host'], $this->url['port']);

        if (true === $this->url['secured']) {
            $protocol = 'ssl';
            $host = 'ssl://' . $host;
        }

        // add custom headers
        if (isset($this->options['headers'])) {
            $headers = isset($this->context[$protocol]['header']) ? $this->context[$protocol]['header'] : [];
            $this->context[$protocol]['header'] = array_merge($headers, $this->options['headers']);
        }

        $this->stream = stream_socket_client($host, $errors[0], $errors[1], $this->options['timeout'], STREAM_CLIENT_CONNECT, stream_context_create($this->context));

        if (!is_resource($this->stream)) {
            throw new SocketException($errors[0], $errors[1]);
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
        $this->session = null;
        $this->cookies = [];
    }

    /** {@inheritDoc} */
    public function emit($event, array $args)
    {
        $namespace = $this->namespace;

        if ('' !== $namespace) {
            $namespace .= ',';
        }

        return $this->write(EngineInterface::MESSAGE, static::EVENT . $namespace . json_encode([$event, $args]));
    }

    /** {@inheritDoc} */
    public function of($namespace) {
        parent::of($namespace);

        $this->write(EngineInterface::MESSAGE, static::CONNECT . $namespace);
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

        $context = $this->context;
        $protocol = true === $this->url['secured'] ? 'ssl' : 'http';

        if (!isset($context[$protocol])) {
            $context[$protocol] = [];
        }

        // add customer headers
        if (isset($this->options['headers'])) {
            $headers = isset($context[$protocol]['header']) ? $context[$protocol]['header'] : [];
            $context[$protocol]['header'] = array_merge($headers, $this->options['headers']);
        }

        $url    = sprintf('%s://%s:%d/%s/?%s', $this->url['scheme'], $this->url['host'], $this->url['port'], trim($this->url['path'], '/'), http_build_query($query));
        $result = @file_get_contents($url, false, stream_context_create($context));

        if (false === $result) {
            $message = null;
            $error = error_get_last();

            if (null !== $error && false !== strpos($error['message'], 'file_get_contents()')) {
                $message = $error['message'];
            }

            throw new ServerConnectionFailureException($message);
        }

        $open_curly_at = strpos($result, '{');
        $todecode = substr($result, $open_curly_at, strrpos($result, '}')-$open_curly_at+1);
        $decoded = json_decode($todecode, true);

        if (!in_array('websocket', $decoded['upgrades'])) {
            throw new UnsupportedTransportException('websocket');
        }

        $cookies = [];
        foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie:\s*([^;]*)/i', $header, $matches)) {
                $cookies[] = $matches[1];
            }
        }
        $this->cookies = $cookies;

        $this->session = new Session($decoded['sid'], $decoded['pingInterval'], $decoded['pingTimeout'], $decoded['upgrades']);
    }

    /**
     * Upgrades the transport to WebSocket
     *
     * FYI:
     * Version "2" is used for the EIO param by socket.io v1
     * Version "3" is used by socket.io v2
     */
    protected function upgradeTransport()
    {
        $query = ['sid'       => $this->session->id,
                  'EIO'       => $this->options['version'],
                  'transport' => static::TRANSPORT_WEBSOCKET];

        if ($this->options['version'] === 2) {
            $query['use_b64'] = $this->options['use_b64'];
        }

        $url = sprintf('/%s/?%s', trim($this->url['path'], '/'), http_build_query($query));

        $hash = sha1(uniqid(mt_rand(), true), true);

        if ($this->options['version'] !== 2) {
            $hash = substr($hash, 0, 16);
        }

        $key = base64_encode($hash);

        $origin = '*';
        $headers = isset($this->context['headers']) ? (array) $this->context['headers'] : [] ;

        foreach ($headers as $header) {
            $matches = [];

            if (preg_match('`^Origin:\s*(.+?)$`', $header, $matches)) {
                $origin = $matches[1];
                break;
            }
        }

        $request = "GET {$url} HTTP/1.1\r\n"
                 . "Host: {$this->url['host']}:{$this->url['port']}\r\n"
                 . "Upgrade: WebSocket\r\n"
                 . "Connection: Upgrade\r\n"
                 . "Sec-WebSocket-Key: {$key}\r\n"
                 . "Sec-WebSocket-Version: 13\r\n"
                 . "Origin: {$origin}\r\n";

        if (!empty($this->cookies)) {
            $request .= "Cookie: " . implode('; ', $this->cookies) . "\r\n";
        }

        $request .= "\r\n";

        fwrite($this->stream, $request);
        $result = fread($this->stream, 12);

        if ('HTTP/1.1 101' !== $result) {
            throw new UnexpectedValueException(sprintf('The server returned an unexpected value. Expected "HTTP/1.1 101", had "%s"', $result));
        }

        // cleaning up the stream
        while ('' !== trim(fgets($this->stream)));

        $this->write(EngineInterface::UPGRADE);

        //remove message '40' from buffer, emmiting by socket.io after receiving EngineInterface::UPGRADE
        if ($this->options['version'] === 2)
            $this->read();
    }
}

