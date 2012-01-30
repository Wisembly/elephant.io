#!/usr/bin/php -q

<?php

require('../ElephantIOClient.class.php');

$elephant = new ElephantIOClient('http://localhost:1337');

$elephant->init();
