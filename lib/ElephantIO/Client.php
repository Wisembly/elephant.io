<?php

namespace ElephantIO;

/**
 * ElephantIOClient is a rough implementation of socket.io protocol.
 * It should ease you dealing with a socket.io server.
 *
 * @author Ludovic Barreca <ludovic@balloonup.com>
 */
class Client {
    const TYPE_DISCONNECT   = 0;
    const TYPE_CONNECT      = 1;
    const TYPE_HEARTBEAT    = 2;
    const TYPE_MESSAGE      = 3;
    const TYPE_JSON_MESSAGE = 4;
    const TYPE_EVENT        = 5;
    const TYPE_ACK          = 6;
    const TYPE_ERROR        = 7;
    const TYPE_NOOP         = 8;

    private $socketIOUrl;
    private $serverHost;
    private $serverPort = 80;
    private $session;
    private $fd;
    private $buffer;
    private $lastId = 0;
    private $read;
    private $checkSslPeer = true;
    private $debug;
    private $handshakeTimeout = null;
    private $callbacks = array();
    private $handshakeQuery = '';

    public function __construct($socketIOUrl, $socketIOPath = 'socket.io', $protocol = 1, $read = true, $checkSslPeer = true, $debug = false) {
        $this->socketIOUrl = $socketIOUrl.'/'.$socketIOPath.'/'.(string)$protocol;
        $this->read = $read;
        $this->debug = $debug;
        $this->parseUrl();
        $this->checkSslPeer = $checkSslPeer;
    }

    /**
     * Set query to be sent during handshake.
     *
     * @param array $query Query paramters as key => value
     * @return Client
     */
    public function setHandshakeQuery(array $query) {
        $this->handshakeQuery = '?' . http_build_query($query);

        return $this;
    }

    /**
     * Initialize a new connection
     *
     * @param boolean $keepalive
     * @return Client
     */
    public function init($keepalive = false) {
        $this->handshake();
        $this->connect();

        if ($keepalive) {
            $this->keepAlive();
        } else {
            return $this;
        }
    }

    /**
     * Keep the connection alive and dispatch events
     *
     * @access public
     * @todo work on callbacks
     */
    public function keepAlive() {
        while (is_resource($this->fd)) {
            if ($this->session['heartbeat_timeout'] > 0 && $this->session['heartbeat_timeout']+$this->heartbeatStamp-5 < time()) {
                $this->send(self::TYPE_HEARTBEAT);
                $this->heartbeatStamp = time();
            }

            $r = array($this->fd);
            $w = $e = null;

            if (stream_select($r, $w, $e, 5) == 0) continue;

            $res = $this->read();
            $sess = explode(':', $res);
            if ((int)$sess[0] === self::TYPE_EVENT) {
                unset($sess[0], $sess[1], $sess[2]);

                $response = json_decode(implode(':', $sess), true);
                $name = $response['name'];
                $data = $response['args'][0];

                $this->stdout('debug', 'Receive event "' . $name . '" with data "' . $data . '"');

                if (!empty($this->callbacks[$name])) {
                    foreach ($this->callbacks[$name] as $callback) {
                        call_user_func($callback, $data);
                    }
                }
            }
        }
    }

    /**
     * Read the buffer and return the oldest event in stack
     *
     * @access public
     * @return string
     * // https://tools.ietf.org/html/rfc6455#section-5.2
     */
    public function read() {
        // Ignore first byte, I hope Socket.io does not send fragmented frames, so we don't have to deal with FIN bit.
        // There are also reserved bit's which are 0 in socket.io, and opcode, which is always "text frame" in Socket.io
        fread($this->fd, 1);

        // There is also masking bit, as MSB, but it's 0 in current Socket.io
        $payload_len = ord(fread($this->fd, 1));

        switch ($payload_len) {
            case 126:
                $payload_len = unpack("n", fread($this->fd, 2));
                $payload_len = $payload_len[1];
                break;
            case 127:
                $this->stdout('error', "Next 8 bytes are 64bit uint payload length, not yet implemented, since PHP can't handle 64bit longs!");
                break;
        }

        // Use buffering to handle packet size > 16Kb
        $read = 0;
        $payload = '';
        while ($read < $payload_len && ($buff = fread($this->fd, $payload_len-$read))) {
            $read += strlen($buff);
            $payload .= $buff;
        }
        $this->stdout('debug', 'Received ' . $payload);

        return $payload;
    }

    /**
     * Attach an event handler function for a given event
     *
     * @access public
     * @param string $event
     * @param callable $callback
     * @return string
     */
    public function on($event, $callback) {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('ElephantIOClient::on() type callback must be callable.');
        }

        if (!isset($this->callbacks[$event])) {
            $this->callbacks[$event] = array();
        }

        // @TODO Handle case where callback is a string
        if (in_array($callback, $this->callbacks[$event])) {
            $this->stdout('debug', 'Skip existing callback');
            return;
        }

        $this->callbacks[$event][] = $callback;
    }

    /**
     * Send message to the websocket
     *
     * @access public
     * @param int $type
     * @param int $id
     * @param int $endpoint
     * @param string $message
     * @return ElephantIO\Client
     */
    public function send($type, $id = null, $endpoint = null, $message = null) {
        if (!is_int($type) || $type > 8) {
            throw new \InvalidArgumentException('ElephantIOClient::send() type parameter must be an integer strictly inferior to 9.');
        }

        $raw_message = $type.':'.$id.':'.$endpoint.':'.$message;
        $payload = new Payload();
        $payload->setOpcode(Payload::OPCODE_TEXT)
            ->setMask(true)
            ->setPayload($raw_message);
        $encoded = $payload->encodePayload();

        fwrite($this->fd, $encoded);

        // wait 100ms before closing connexion
        usleep(100*1000);

        $this->stdout('debug', 'Sent '.$raw_message);

        return $this;
    }

    /**
     * Emit an event
     *
     * @param string $event
     * @param array $args
     * @param string $endpoint
     * @param function $callback - ignored for the time being
     * @return ElephantIO\Client
     * @todo work on callbacks
     */
    public function emit($event, $args, $endpoint = null, $callback = null) {
        return $this->send(self::TYPE_EVENT, null, $endpoint, json_encode(array(
            'name' => $event,
            'args' => $args,
            )
        ));
    }

    /**
     * Close the socket
     *
     * @return boolean
     */
    public function close()
    {
        if (is_resource($this->fd)) {
            $this->send(self::TYPE_DISCONNECT);
            fclose($this->fd);

            return true;
        }

        return false;
    }

    /**
     * Send ANSI formatted message to stdout.
     * First parameter must be either debug, info, error or ok
     *
     * @access private
     * @param string $type
     * @param string $message
     */
    private function stdout($type, $message) {
        if (!defined('STDOUT') || !$this->debug) {
            return false;
        }

        $typeMap = array(
            'debug'   => array(36, '- debug -'),
            'info'    => array(37, '- info  -'),
            'error'   => array(31, '- error -'),
            'ok'      => array(32, '- ok    -'),
        );

        if (!array_key_exists($type, $typeMap)) {
            throw new \InvalidArgumentException('ElephantIOClient::stdout $type parameter must be debug, info, error or success. Got '.$type);
        }

        fwrite(STDOUT, "\033[".$typeMap[$type][0]."m".$typeMap[$type][1]."\033[37m  ".$message."\r\n");
    }

    private function generateKey($length = 16) {
        $c = 0;
        $tmp = '';

        while($c++ * 16 < $length) {
            $tmp .= md5(mt_rand(), true);
        }

        return base64_encode(substr($tmp, 0, $length));
    }

    /**
     * Set Handshake timeout in milliseconds
     *
     * @param int $delay
     */
    public function setHandshakeTimeout($delay) {
        $this->handshakeTimeout = $delay;
    }

    /**
     * Handshake with socket.io server
     *
     * @access private
     * @return bool
     */
    private function handshake() {
        $url = $this->socketIOUrl;

        if (!empty($this->handshakeQuery)) {
            $url .= $this->handshakeQuery;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (!$this->checkSslPeer) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        if (null !== $this->handshakeTimeout) {
            $timeout   = $this->handshakeTimeout;
            $constants = array(CURLOPT_CONNECTTIMEOUT_MS, CURLOPT_TIMEOUT_MS);

            $version = curl_version();
            $version = $version['version'];

            // CURLOPT_CONNECTTIMEOUT_MS and CURLOPT_TIMEOUT_MS were only implemented on curl 7.16.2
            if (true === version_compare($version, '7.16.2', '<')) {
                $timeout  /= 1000;
                $constants = array(CURLOPT_CONNECTTIMEOUT, CURLOPT_TIMEOUT);
            }

            curl_setopt($ch, $constants[0], $timeout);
            curl_setopt($ch, $constants[1], $timeout);
        }

        $res = curl_exec($ch);

        if ($res === false || $res === '') {
            throw new \Exception(curl_error($ch));
        }

        $sess = explode(':', $res);
        $this->session['sid'] = $sess[0];
        $this->session['heartbeat_timeout'] = $sess[1];
        $this->session['connection_timeout'] = $sess[2];
        $this->session['supported_transports'] = array_flip(explode(',', $sess[3]));

        if (!isset($this->session['supported_transports']['websocket'])) {
            throw new \Exception('This socket.io server do not support websocket protocol. Terminating connection...');
        }

        return true;
    }

    /**
     * Connects using websocket protocol
     *
     * @access private
     * @return bool
     */
    private function connect() {
        $this->fd = fsockopen($this->serverHost, $this->serverPort, $errno, $errstr);

        if (!$this->fd) {
            throw new \Exception('fsockopen returned: '.$errstr);
        }

        $key = $this->generateKey();

        $out  = "GET ".$this->serverPath."/websocket/".$this->session['sid']." HTTP/1.1\r\n";
        $out .= "Host: ".$this->serverHost."\r\n";
        $out .= "Upgrade: WebSocket\r\n";
        $out .= "Connection: Upgrade\r\n";
        $out .= "Sec-WebSocket-Key: ".$key."\r\n";
        $out .= "Sec-WebSocket-Version: 13\r\n";
        $out .= "Origin: *\r\n\r\n";

        fwrite($this->fd, $out);

        $res = fgets($this->fd);

        if ($res === false) {
            throw new \Exception('Socket.io did not respond properly. Aborting...');
        }

        if ($subres = substr($res, 0, 12) != 'HTTP/1.1 101') {
            throw new \Exception('Unexpected Response. Expected HTTP/1.1 101 got '.$subres.'. Aborting...');
        }

        while(true) {
            $res = trim(fgets($this->fd));
            if ($res === '') break;
        }

        if ($this->read) {
            if ($this->read() != '1::') {
                throw new \Exception('Socket.io did not send connect response. Aborting...');
            } else {
                $this->stdout('info', 'Server report us as connected !');
            }
        }

//        $this->send(self::TYPE_CONNECT);
        $this->heartbeatStamp = time();
    }

    /**
     * Parse the url and set server parameters
     *
     * @access private
     * @return bool
     */
    private function parseUrl() {
        $url = parse_url($this->socketIOUrl);

        $this->serverPath = $url['path'];
        $this->serverHost = $url['host'];
        $this->serverPort = isset($url['port']) ? $url['port'] : null;

        if (array_key_exists('scheme', $url) && $url['scheme'] == 'https') {
            $this->serverHost = 'ssl://'.$this->serverHost;
            if (!$this->serverPort) {
                $this->serverPort = 443;
            }
        }

        return true;
    }
}
