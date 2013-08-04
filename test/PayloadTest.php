<?php
namespace ElephantIO;

use PHPUnit_Framework_TestCase;

class PayloadTest extends PHPUnit_Framework_TestCase {

	public function testGenerateMaskKey() {
		$payload = new Payload();

		$key1 = $payload->generateMaskKey();
		$key2 = $payload->generateMaskKey();

		$this->assertEquals(4, strlen($key1));
		$this->assertEquals(4, strlen($key2));
		$this->assertFalse($key1 == $key2);
	}

	public function testMaskData() {
		$payload = new Payload();

		$key = '?ΕV';
		$data = 'hello';
		$expected = '57abf93a50';

		$this->assertEquals($expected, bin2hex($payload->maskData($data, $key)));
	}

	public function testPayloadShortLength() {
		$payload = new Payload();

		$payload->setOpcode(Payload::OPCODE_TEXT);
		$payload->setPayload('toto');

		$this->assertEquals('8104746f746f', bin2hex($payload->encodePayload()));
	}

	public function testPayloadAverageLength() {
		$payload = new Payload();

		$payload->setOpcode(Payload::OPCODE_TEXT);
		$payload->setPayload(
			"This payload length is over 125 chars, ".
			"hence the length part inside the payload should now be 16 bits in length. ".
			"The next test will include an even more longer string to test the ability ".
			"to encode even more longer data.
			");

		$expected = '817e00df54686973207061796c6f6164206c656e677468206973206f76';
		$expected .= '6572203132352063686172732c2068656e636520746865206c656e677';
		$expected .= '468207061727420696e7369646520746865207061796c6f6164207368';
		$expected .= '6f756c64206e6f77206265203136206269747320696e206c656e67746';
		$expected .= '82e20546865206e65787420746573742077696c6c20696e636c756465';
		$expected .= '20616e206576656e206d6f7265206c6f6e67657220737472696e67207';
		$expected .= '46f207465737420746865206162696c69747920746f20656e636f6465';
		$expected .= '206576656e206d6f7265206c6f6e67657220646174612e0a090909';

		$this->assertEquals($expected, bin2hex($payload->encodePayload()));
	}

	public function testEncodedPayloadShortLength() {
		$payload = new Payload();

		$payload->setOpCode(Payload::OPCODE_TEXT);
		$payload->setMask(true);
		$payload->setPayload('toto');
		$payload->setMaskKey('?ΕV');

		$this->assertEquals('81843fce95564ba1e139', bin2hex($payload->encodePayload()));
	}

	public function testEncodedPayloadAverageLength() {
		$payload = new Payload();

		$payload->setOpCode(Payload::OPCODE_TEXT);
		$payload->setMask(true);
		$payload->setMaskKey('?ΕV');
		$payload->setPayload(
			" This payload length is over 125 chars, ".
			"hence the length part inside the payload should now be 16 bits in length. ".
			"The next test will include an even more longer string to test the ability ".
			"to encode even more longer data."
		);
		$expected = '81fe00dc3fce95561f9afd3f4ceee53746a2fa375beef93351a9e13e1fa7';
		$expected .= 'e67650b8f0241fffa7631fadfd374dbdb97657abfb355aeee13e5aeef93';
		$expected .= '351a9e13e1fbef4244beefc384ca7f1331fbafd331fbef42f53a1f4321f';
		$expected .= 'bdfd394aa2f17651a1e2765dabb56709eef73f4bbdb53f51eef93351a9e';
		$expected .= '13e11eec13e5aeefb3347bab5225abde17648a7f93a1fa7fb3553bbf133';
		$expected .= '1faffb765ab8f0381fa3fa245aeef93951a9f0241fbde12456a0f2764ba';
		$expected .= '1b5225abde1764ba6f0765eacfc3a56baec764ba1b53351adfa325aeef0';
		$expected .= '205aa0b53b50bcf07653a1fb315abcb5325ebaf478';


		$this->assertEquals($expected, bin2hex($payload->encodePayload()));
	}
}

