<?php

require( __DIR__ . '/../lib/ElephantIO/Client.php');
use ElephantIO\Client as ElephantIOClient;

$elephant = new ElephantIOClient('http://localhost:8000', 'socket.io', 1, false, true, true);

$elephant->init();
$elephant->send(
    ElephantIOClient::TYPE_EVENT,
    null,
    null,
    json_encode(array('name' => 'action', 'args' => 'foo'))
);
$elephant->close();

echo 'tryin to send `foo` to the event called action';
