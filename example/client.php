#!/usr/bin/php -q

<?php

require('../SocketIOClient.class.php');

$elephant = new SocketIOClient('http://localhost:1337');

$elephant->init();
