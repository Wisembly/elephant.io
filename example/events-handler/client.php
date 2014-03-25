<?php
require( __DIR__ . '/../../lib/ElephantIO/Client.php');
use ElephantIO\Client as ElephantIOClient;

function callbackTest($data) {
    var_dump($data);
}

$elephant = new ElephantIOClient('http://localhost:8000', 'socket.io', 1, false, true, true);

$elephant->init();
$elephant->emit('ping', 'What is the answer to life the universe and everything ?');
$elephant->on('pong', callbackTest);
$elephant->on('pong', callbackTest); // Duplicates are skipped
$elephant->keepAlive();
