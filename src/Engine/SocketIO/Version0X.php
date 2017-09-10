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
 * Implements the dialog with Socket.IO version 0.x
 *
 * Based on the work of Baptiste Clavié (@Taluu)
 *
 * @auto ByeoungWook Kim <quddnr145@gmail.com>
 * @link https://tools.ietf.org/html/rfc6455#section-5.2 Websocket's RFC
 */
class Version0X extends AbstractSocketIO
{
    const CLOSE         = 0;
    const OPEN          = 1;
    const HEARTBEAT     = 2;
    const MESSAGE       = 3;
    const JOIN_MESSAGE  = 4;
    const EVENT         = 5;
    const ACK           = 6;
    const ERROR         = 7;
    const NOOP          = 8;

    const TRANSPORT_POLLING   = 'xhr-polling';
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
            $host = 'ssl://' . $host;
            $protocol = 'ssl';
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

        $this->write(static::CLOSE);
        fclose($this->stream);
        $this->stream = null;
        $this->session = null;
        $this->cookies = [];
    }

    /** {@inheritDoc} */
    public function emit($event, array $args)
    {
        $this->write(static::EVENT, json_encode(['name' => $event, 'args' => $args]));
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

        $payload = new Encoder($code . '::' . $this->namespace . ':' . $message, Encoder::OPCODE_TEXT, true);
        $bytes = fwrite($this->stream, (string) $payload);

        // wait a little bit of time after this message was sent
        usleep($this->options['wait']);

        return $bytes;
    }

    /** {@inheritDoc} */
    public function of($namespace) {
        parent::of($namespace);

        $this->write(static::OPEN);
    }

    /** {@inheritDoc} */
    public function getName()
    {
        return 'SocketIO Version 0.X';
    }

    /** {@inheritDoc} */
    protected function getDefaultOptions()
    {
        $defaults = parent::getDefaultOptions();

        $defaults['protocol']  = 1;
        $defaults['transport'] = static::TRANSPORT_WEBSOCKET;

        return $defaults;
    }

    /** Does the handshake with the Socket.io server and populates the `session` value object */
    protected function handshake()
    {
        if (null !== $this->session) {
            return;
        }

        $context = $this->context;
        $protocol = $this->url['secured'] ? 'ssl' : 'http';

        if (!isset($context[$protocol])) {
            $context[$protocol] = [];
        }

        $context[$protocol]['timeout'] = (float) $this->options['timeout'];

        // add custom headers
        if (!empty($this->options['headers'])) {
            $headers = !empty($context[$protocol]['header']) ? $context[$protocol]['header'] : [];
            $context[$protocol]['header'] = array_merge($headers, $this->options['headers']);
        }

        $url = sprintf('%s://%s:%d/%s/%d', $this->url['scheme'], $this->url['host'], $this->url['port'], trim($this->url['path'], '/'), $this->options['protocol']);

        if (isset($this->url['query'])) {
            $url .= '/?' . http_build_query($this->url['query']);
        }

        $result = @file_get_contents($url, false, stream_context_create($context));

        if (false === $result) {
            $message = null;
            $error = error_get_last();

            if (null !== $error && false !== strpos($error['message'], 'file_get_contents()')) {
                $message = $error['message'];
            }

            throw new ServerConnectionFailureException($message);
        }

        $sess = explode(':', $result);
        $decoded['sid'] = $sess[0];
        $decoded['pingInterval'] = $sess[1];
        $decoded['pingTimeout'] = $sess[2];
        $decoded['upgrades'] = array_flip(explode(',', $sess[3]));

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

    /** Upgrades the transport to WebSocket */
    private function upgradeTransport()
    {
        if (!array_key_exists('websocket', $this->session->upgrades)) {
            return new UnsupportedTransportException('websocket');
        }

        $url = sprintf('/%s/%d/%s/%s', trim($this->url['path'], '/'), $this->options['protocol'], $this->options['transport'], $this->session->id);
        if (isset($this->url['query'])) {
            $url .= '/?' . http_build_query($this->url['query']);
        }

        $key = base64_encode(sha1(uniqid(mt_rand(), true), true));

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
                 . "Host: {$this->url['host']}\r\n"
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
    }
}

