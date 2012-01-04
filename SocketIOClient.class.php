<?php

/**
 * SocketIOClient is a rough implementation of socket.io protocol.
 * It should ease you dealing with a socket.io server.
 *
 * @author Ludovic Barreca <ludovic@balloonup.com>
 */
class SocketIOClient {
    private $serverUrl;
    private $session;

    public function __construct($serverUrl) {
        $this->serverUrl = $serverUrl;
        $this->handshake();
    }

    /**
     * Handshake with socket.io server
     *
     * @return bool
     */
    public function handshake() {
        $ch = curl_init($this->serverUrl);
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

        if (!isset($session['supported_transports']['websocket'])) {
            throw new \Exception('This socket.io server do not support websocket protocol. Terminating connection...');
        }

        return true;
    }
}
