#!/usr/bin/php -q

<?php

require('ElephantIOClient.class.php');

$elephant = new ElephantIOClient('http://localhost:1337');

$elephant->init(false);
$elephant->send(ElephantIOClient::TYPE_MESSAGE, null, null, 'Hello World!');
