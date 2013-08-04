<?php
/**
 * https://github.com/mrniko/netty-socketio
 */

require_once __DIR__.'/../vendor/autoload.php';

use ElephantIO\Client as ElephantIOClient;

$elephant = new ElephantIOClient('http://localhost:9092', 'socket.io', 1, false, true, true);

$elephant->init();

$elephant->send(
	ElephantIOClient::TYPE_JSON_MESSAGE,
	null,
	null,
	json_encode(array(
		'@class' => 'com.corundumstudio.socketio.demo.ChatObject',
		'userName' => 'user444',
		'message' => 'message'
	))
);

$elephant->close();
