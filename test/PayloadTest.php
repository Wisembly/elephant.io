<?php

require_once(__DIR__.'/../lib/ElephantIO/Payload.php');

class PayloadTest extends PHPUnit_Framework_TestCase
{
    public function testGenerateMaskKey() {
        $payload = new ElephantIO\Payload();

        $key1 = $payload->generateMaskKey();
        $key2 = $payload->generateMaskKey();

        $this->assertEquals(4, strlen($key1));
        $this->assertEquals(4, strlen($key2));
        $this->assertFalse($key1 == $key2);
    }

    public function testMaskData() {
        $payload = new ElephantIO\Payload();

        $key = '?ΕV';
        $data = 'hello';
        $expected = '57abf93a50';

        $this->assertEquals($expected, bin2hex($payload->maskData($data, $key)));
    }

    public function testPayloadShortLength() {
        $payload = new ElephantIO\Payload();

        $payload->setOpcode(ElephantIO\Payload::OPCODE_TEXT);
        $payload->setPayload('toto');

        $this->assertEquals('8104746f746f', bin2hex($payload->encodePayload()));
    }

    public function testPayloadAverageLength() {
        $payload = new ElephantIO\Payload();

        $payload->setOpcode(ElephantIO\Payload::OPCODE_TEXT);
        $payload->setPayload(<<<EOF
This payload length is over 125 chars, 
hence the length part inside the payload should now be 16 bits in length. 
The next test will include an even more longer string to test the ability 
to encode even more longer data.
EOF
);
        $expected = '817e00de54686973207061796c6f6164206c656e677468206973206f76';
        $expected .= '6572203132352063686172732c200a68656e636520746865206c656e6';
        $expected .= '77468207061727420696e7369646520746865207061796c6f61642073';
        $expected .= '686f756c64206e6f77206265203136206269747320696e206c656e677';
        $expected .= '4682e200a546865206e65787420746573742077696c6c20696e636c75';
        $expected .= '646520616e206576656e206d6f7265206c6f6e67657220737472696e6';
        $expected .= '720746f207465737420746865206162696c697479200a746f20656e63';
        $expected .= '6f6465206576656e206d6f7265206c6f6e67657220646174612e';


        $this->assertEquals($expected, bin2hex($payload->encodePayload()));
    }

     public function testEncodedPayloadShortLength() {
        $payload = new ElephantIO\Payload();

        $payload->setOpCode(ElephantIO\Payload::OPCODE_TEXT);
        $payload->setMask(true);
        $payload->setPayload('toto');
        $payload->setMaskKey('?ΕV');

        $this->assertEquals('81843fce95564ba1e139', bin2hex($payload->encodePayload()));
    }

    public function testEncodedPayloadAverageLength() {
        $payload = new ElephantIO\Payload();

        $payload->setOpCode(ElephantIO\Payload::OPCODE_TEXT);
        $payload->setMask(true);
        $payload->setMaskKey('?ΕV');
        $payload->setPayload(<<<EOF
This payload length is over 125 chars, 
hence the length part inside the payload should now be 16 bits in length. 
The next test will include an even more longer string to test the ability 
to encode even more longer data.
EOF
);
        $expected = '81fe00de3fce95566ba6fc251fbef42f53a1f4321fa2f03858bafd7656';
        $expected .= 'bdb53949abe7760efca0765ca6f4244ce2b55c57abfb355aeee13e5ae';
        $expected .= 'ef93351a9e13e1fbef4244beefc384ca7f1331fbafd331fbef42f53a1';
        $expected .= 'f4321fbdfd394aa2f17651a1e2765dabb56709eef73f4bbdb53f51eef';
        $expected .= '93351a9e13e11ee9f0257abb5385ab6e1764babe6221fb9fc3a53eefc';
        $expected .= '385ca2e0325aeef4381fabe33351eef8394dabb53a50a0f2334deee62';
        $expected .= '24da7fb311fbafa764babe6221fbafd331faff73f53a7e12f1fc4e139';
        $expected .= '1fabfb3550aaf0765ab8f0381fa3fa245aeef93951a9f0241faaf4225';
        $expected .= 'ee0';

        $this->assertEquals($expected, bin2hex($payload->encodePayload()));
    }
}

