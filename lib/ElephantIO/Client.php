<?php

namespace ElephantIO;

require_once(__DIR__.'/Payload.php');
require_once(__DIR__.'/Frame.php');

/**
 *
 * NAMESPACES (endpoints)
 *
 * emit and send method automatically register socket into received endpoint
 *
 */

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

	public $origin = '*';
	public $cookie;
	public $sendCookie = false;

    private $socketIOUrl;
    private $serverHost;
    private $serverPort = 80;
	private $serverPath;
    private $session;
    private $fd;
    private $buffer;
    private $lastId = 0;
    private $read;
    private $checkSslPeer = true;
    private $debug;
    private $handshakeTimeout = null;
	private $endpoints = array();

    public function __construct($socketIOUrl, $socketIOPath = 'socket.io', $protocol = 1, $read = true, $checkSslPeer = true, $debug = false) {
        $this->socketIOUrl = $socketIOUrl.'/'.$socketIOPath.'/'.(string)$protocol;
        $this->read = $read;
        $this->debug = $debug;
        $this->parseUrl();
        $this->checkSslPeer = $checkSslPeer;
    }

    /**
     * Initialize a new connection
     *
     * @param boolean $keepalive
     * @return ElephantIOClient
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
        while(true) {
            if ($this->session['heartbeat_timeout'] > 0 && $this->session['heartbeat_timeout']+$this->heartbeatStamp-5 < time()) {
                $this->send(self::TYPE_HEARTBEAT);
                $this->heartbeatStamp = time();
            }

            $r = array($this->fd);
            $w = $e = null;

            if (stream_select($r, $w, $e, 5) == 0) continue;

            $this->read();
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

        $payload = fread($this->fd, $payload_len);
        $this->stdout('debug', 'Received ' . $payload);

        return $payload;
    }

	/**
	 * Join into socket.io namespace
	 *
	 * EXAMPLE:
	 * $client = new \ElephantIO\Client();
	 * ...
	 * //   for entering in some namespace
	 * $client->of('/event');
	 * or you can use
	 *  $client->emit();
	 *    and
	 *  $client->send();
	 *
	 * if you are not in some endpoint, you will automatically enter
	 *
	 *
	 * @param string $endpoint
	 *
	 * @return Client
	 */
	public function of($endpoint = null) {
		if ($endpoint && !in_array($endpoint, $this->endpoints)) {
			$data = self::TYPE_CONNECT . '::' . $endpoint;
			$this->write($this->encode($data));
			$this->endpoints[] = $endpoint;
		}
		return $this;
	}

	/**
	 * @return Client
	 */
	public function leaveEndpoint($endpoint) {
		if ($endpoint && in_array($endpoint, $this->endpoints)) {
			$data = self::TYPE_DISCONNECT . '::' . $endpoint;
			$this->write($this->encode($data));
			unset($this->endpoints[array_search($endpoint, $this->endpoints)]);
		}
		return $this;
	}

	/**
	 * @return Frame
	 */
	public function createFrame($type = null, $endpoint = null) {
		return new Frame($this, $type, $endpoint);
	}

	/**
	 * @param Frame $frame
	 */
	public function sendFrame(Frame $frame) {
		$this->send(
			$frame->getType(),
			$frame->getId(),
			$frame->getEndPoint(),
			$frame->getData()
		);
	}

    /**
     * Send message to the websocket
     *
     * @access public
     * @param int $type
     * @param int $id
     * @param int $endpoint
     * @param string $message
     * @return \ElephantIO\Client
     */
    public function send($type, $id = null, $endpoint = null, $message = null) {
	    if (!is_int($type) || $type < 0 || $type > 8) {
		    throw new \InvalidArgumentException('ElephantIOClient::send() type parameter must be an integer strictly inferior to 9.');
	    }
	    $this->of($endpoint);
	    $raw_message = $type . ':' . $id . ':' . $endpoint . ':' . $message;
	    $this->write($this->encode($raw_message));

	    $this->stdout('debug', 'Sent ' . $raw_message);

	    return $this;
    }

    /**
     * Emit an event
     *
     * @param string $event
     * @param array $args
     * @param string $endpoint
     * @param function $callback - ignored for the time being
     * @todo work on callbacks
     */
    public function emit($event, $args, $endpoint, $callback = null) {
        $this->send(self::TYPE_EVENT, null, $endpoint, json_encode(array(
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
	public function close() {
		if ($this->fd) {
			$this->write($this->encode(self::TYPE_DISCONNECT, Payload::OPCODE_CLOSE), false);
			fclose($this->fd);
			return true;
		}
		return false;
	}

	protected function write($data, $sleep = true) {
		fwrite($this->getSocket(), $data);
		// wait 100ms before closing connexion
		if ($sleep) {
			usleep(100 * 1000);
		}
		return $this;
	}

	/**
	 * @return mixed
	 * @throws \RuntimeException
	 */
	private function getSocket() {
		if (!$this->fd) {
			throw new \RuntimeException('The connection is lost');
		}
		return $this->fd;
	}

	/**
	 * @param      $message
	 * @param int  $opCode
	 * @param bool $mask
	 *
	 * @return string
	 */
	private function encode($message, $opCode = Payload::OPCODE_TEXT, $mask = true) {
		$payload = new Payload();
		return $payload
				->setOpcode($opCode)
				->setMask($mask)
				->setPayload($message)
				->encodePayload();
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
	 * @return string
	 */
	private function getOrigin() {
		$origin = "Origin: *\n\n";
		if ($this->origin) {
			if (strpos($this->origin, 'http://') === false) {
				$origin = sprintf("Origin: http://%s\n\n", $this->origin);
			} else {
				$origin = sprintf("Origin: %s\n\n", $this->origin);
			}
		}
		return $origin;
	}

    /**
     * Handshake with socket.io server
     *
     * @access private
     * @return bool
     */
    private function handshake() {
        $ch = curl_init($this->socketIOUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (!$this->checkSslPeer)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (!is_null($this->handshakeTimeout)) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->handshakeTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->handshakeTimeout);
        }

	    if ($this->origin) {
		    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			    $this->getOrigin()
		    ));
	    }

	    if ($this->sendCookie && $this->cookie) {
		    curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
	    }

        $res = curl_exec($ch);

        if ($res === false || $res === '') {
            throw new \Exception(curl_error($ch));
        }

	    if ($res == 'handshake bad origin') {
		    throw new \Exception('Handshake error: bad origin');
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
	    if ($this->sendCookie && $this->cookie) {
		    $out .= "Cookie: " . $this->cookie . "\r\n";
	    }
	    $out .= $this->getOrigin();

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
