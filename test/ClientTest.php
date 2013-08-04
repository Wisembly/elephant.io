<?php
namespace ElephantIO;

use ElephantIO\Client;
use PHPUnit_Framework_TestCase;
use ReflectionMethod;

class ClientTest extends PHPUnit_Framework_TestCase {

	public function testGenerateKey() {
		$reflectionMethod = new ReflectionMethod('ElephantIO\Client', 'generateKey');
		$reflectionMethod->setAccessible(true);

		$key = $reflectionMethod->invoke(new Client('http://localhost.net'));

		$this->assertEquals(24, strlen($key));
	}
}

