#!/usr/bin/php -q

<?php

require_once('lib/ElephantIO/Client.php');

$elephant = new ElephantIO\Client('https://localhost:447', 'socket.io', 1, false);

$elephant->init(false, false);
$elephant->send(ElephantIO\Client::TYPE_HEARTBEAT, null, null, null);

