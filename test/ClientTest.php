<?php

require_once(__DIR__.'/../lib/ElephantIO/Client.php');

class ClientTest extends PHPUnit_Framework_TestCase
{
    public function testGenerateKey() {
        $client = new ElephantIO\Client('http://localhost.net');

        $key = $client->generateKey();

        $this->assertEquals(24, strlen($key));
    }
}

