<?php

require __DIR__ . '/vendor/autoload.php';

use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version2X;

$options = [
    'context' => [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ],
	    'http' => [
	    	'header'=>[
	    		'token: xxxxxx'
	    	],
	    ]
    ]
];

$client = new Client(new Version2X('https://localhost:1337',$options));
$client->initialize();
// send message to connected clients
$client->emit('broadcast', ['type' => 'notification', 'text' => 'Hello There!']);
$client->close();
