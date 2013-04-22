<?php

require_once(__DIR__.'/../lib/ElephantIO/Client.php');

use ElephantIO\Client;

class ClientTest extends PHPUnit_Framework_TestCase
{
    public function testGenerateKey() {
        $reflectionMethod = new ReflectionMethod('ElephantIO\Client', 'generateKey');
        $reflectionMethod->setAccessible(true);

        $key = $reflectionMethod->invoke(new Client('http://localhost.net'));

        $this->assertEquals(24, strlen($key));
    }
}

