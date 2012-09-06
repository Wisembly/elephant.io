#!/usr/bin/php -q

<?php

require('lib/ElephantIO/Client.php');
require('lib/ElephantIO/Payload.php');

$elephant = new ElephantIO\Client('http://localhost:1337', 'socket.io', 1, false);

$elephant->init(false);
$elephant->send(ElephantIO\Client::TYPE_MESSAGE, null, null, 'Hello World!');

use ElephantIO\Payload as Payload;

$mask = 0xAF149B;
$message = "2:::";

$payload = new Payload();
$payload->setOpcode(Payload::OPCODE_TEXT)
    ->setLength(strlen($message))
    ->setMaskKey($mask)
    ->setPayload($message)
;

var_dump($payload->encodePayload());
