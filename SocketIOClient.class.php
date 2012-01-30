<?php

/**
 * SocketIOClient is a rough implementation of socket.io protocol.
 * It should ease you dealing with a socket.io server.
 *
 * @author Ludovic Barreca <ludovic@balloonup.com>
 */
class SocketIOClient {
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

    public function __construct($socketIOUrl, $protocol = 1) {
        $this->socketIOUrl = $socketIOUrl.'/socket.io/'.(string)$protocol;
        $this->parseUrl();
    }

    /**
     * Initialize a new connection
     *
     */
    public function init() {
        $this->handshake();
        $this->connect();
        $this->keepAlive();
    }

    /**
     * Handshake with socket.io server
     *
     * @access public
     * @return bool
     */
    public function handshake() {
        $ch = curl_init($this->socketIOUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);

        if ($res === false) {
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
     * @access public
     * @return bool
     */
    public function connect() {
        $this->fd = fsockopen($this->serverHost, $this->serverPort, $errno, $errstr);

        if (!$this->fd) {
            throw new \Exception('fsockopen returned: '.$errstr);
        }

        $out  = "GET /socket.io/1/websocket/".$this->session['sid']." HTTP/1.1\r\n";
        $out .= "Upgrade: WebSocket\r\n";
        $out .= "Connection: Upgrade\r\n";
        $out .= "Host: ".$this->serverHost."\r\n";
        $out .= "Origin: *\r\n\r\n";
        fwrite($this->fd, $out);
        $res = fgets($this->fd);

        if ($res === false) {
            throw new \Exception('socket.io did not respond properly. Aborting...');
        }

        if ($subres = substr($res, 0, 12) != 'HTTP/1.1 101') {
            throw new \Exception('Unexpected Response. Expected HTTP/1.1 101 got '.$subres.'. Aborting...');
        }

        while(true) {
            $res = trim(fgets($this->fd));
            if ($res === '') break;
        }

        if ($this->read() != '1::') {
            throw new \Exception('Socket.io did not send connect response. Aborting...');
        } else {
            $this->stdout('info', 'Server report us as connected !');
        }

        $this->send(self::TYPE_CONNECT);
        $this->stdout('debug', 'Sent connect confirmation');
        $this->heartbeatStamp = time();
    }

    /**
     * Keep the connection alive and dispatch events
     *
     * @access public
     */
    public function keepAlive() {
        while(true) {
            if ($this->session['heartbeat_timeout'] > 0 && $this->session['heartbeat_timeout']+$this->heartbeatStamp-5 < time()) {
                $this->send(self::TYPE_HEARTBEAT);
                $this->stdout('debug', 'Sent heartbeat packet');
                $this->heartbeatStamp = time();
            }


            $this->read();
        }
    }

    /**
     * Read the buffer and return the oldest event in stack
     *
     * @access public
     * @return string
     */
    public function read() {
        return '1::';
    }

    /**
     * Send message to the websocket
     *
     * @access public
     * @param int $type
     * @param int $id
     * @param int $endpoint
     * @param string $message
     */
    public function send($type, $id = null, $endpoint = null, $message = null) {
        if (!is_int($type) || $type > 8) {
            throw new \InvalidArgumentException('SocketIOClient::send() type parameter must be an integer strictly inferior to 9.');
        }

        fwrite($this->fd, "\x00".$type.":".$id.":".$endpoint.":".$message."\xff");
    }

    public function stdout($type, $message) {
        $typeMap = array(
            'debug'   => array(36, '- debug -'),
            'info'    => array(37, '- info  -'),
            'error'   => array(31, '- error -'),
            'ok'      => array(32, '- ok    -'),
        );

        if (!array_key_exists($type, $typeMap)) {
            throw new \InvalidArgumentException('SocketIOClient.class.php::stdout $type parameter must be debug, info, error or success. Got '.$type);
        }

        fwrite(STDOUT, "\033[".$typeMap[$type][0]."m".$typeMap[$type][1]."\033[37m  ".$message."\r\n");
    }

    /**
     * Parse the url and set server parameters
     *
     * @access private
     * @return bool
     */
    private function parseUrl() {
        $url = parse_url($this->socketIOUrl);
        $this->serverHost = $url['host'];
        $this->serverPort = isset($url['port']) ? $url['port'] : null;

        if ($url['scheme'] == 'https') {
            $this->serverHost = 'ssl://'.$this->serverHost;
            if (!$this->serverPort) {
                $this->serverPort = 443;
            }
        }

        return true;
    }
}
