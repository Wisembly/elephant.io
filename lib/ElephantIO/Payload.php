<?php

namespace ElephantIO;

class Payload
{
    const OPCODE_CONTINUE = 0x0;
    const OPCODE_TEXT = 0x1;
    const OPCODE_BINARY = 0x2;
    const OPCODE_NON_CONTROL_RESERVED_1 = 0x3;
    const OPCODE_NON_CONTROL_RESERVED_2 = 0x4;
    const OPCODE_NON_CONTROL_RESERVED_3 = 0x5;
    const OPCODE_NON_CONTROL_RESERVED_4 = 0x6;
    const OPCODE_NON_CONTROL_RESERVED_5 = 0x7;
    const OPCODE_CLOSE = 0x8;
    const OPCODE_PING = 0x9;
    const OPCODE_PONG = 0xA;
    const OPCODE_CONTROL_RESERVED_1 = 0xB;
    const OPCODE_CONTROL_RESERVED_2 = 0xC;
    const OPCODE_CONTROL_RESERVED_3 = 0xD;
    const OPCODE_CONTROL_RESERVED_4 = 0xE;
    const OPCODE_CONTROL_RESERVED_5 = 0xF;

    private $fin = 0x1;
    private $rsv1 = 0x0;
    private $rsv2 = 0x0;
    private $rsv3 = 0x0;
    private $opcode;
    private $mask = 0x0;
    private $maskKey;
    private $payload;

    public function setFin($fin) {
        $this->fin = $fin;

        return $this;
    }

    public function getFin() {
        return $this->fin;
    }

    public function setRsv1($rsv1) {
        $this->rsv1 = $rsv1;

        return $this;
    }

    public function getRsv1() {
        return $this->rsv1;
    }

    public function setRsv2($rsv2) {
        $this->rsv2 = $rsv2;

        return $this;
    }

    public function getRsv2() {
        return $this->rsv2;
    }

    public function setRsv3($rsv3) {
        $this->rsv3 = $rsv3;

        return $this;
    }

    public function getRsv3() {
        return $this->rsv3;
    }

    public function setOpcode($opcode) {
        $this->opcode = $opcode;

        return $this;
    }

    public function getOpcode() {
        return $this->opcode;
    }

    public function setMask($mask) {
        $this->mask = $mask;

        if ($this->mask == true) {
            $this->generateMaskKey();
        }

        return $this;
    }

    public function getMask() {
        return $this->mask;
    }

    public function getLength() {
        return strlen($this->getPayload());
    }

    public function setMaskKey($maskKey) {
        $this->maskKey = $maskKey;

        return $this;
    }

    public function getMaskKey() {
        return $this->maskKey;
    }

    public function setPayload($payload) {
        $this->payload = $payload;

        return $this;
    }

    public function getPayload() {
        return $this->payload;
    }

    public function generateMaskKey() {
        $this->setMaskKey($key = openssl_random_pseudo_bytes(4));

        return $key;
    }

    public function encodePayload()
    {
        $rawMessage = $this->getPayload();
        if (null !== $rawMessage) return;

        $opcode = $this->getOpcode();
        $length = $this->getLength();

        $fin  = $this->getFin();
        $rsv1 = $this->getRsv1();
        $rsv2 = $this->getRsv2();
        $rsv3 = $this->getRsv3();
        $mask = $this->getMask();
        $extn = 0x0;
        if ($length > 125) {
            $extn = $length;
            $length = ($length <= 0xFFFF)? 126: 127;
        }

        $encodeData = (($fin) << 1) | ($rsv1);
        $encodeData = (($encodeData) << 1) | ($rsv2);
        $encodeData = (($encodeData) << 1) | ($rsv3);
        $encodeData = (($encodeData) << 4) | ($opcode);
        $encodeData = (($encodeData) << 1) | ($mask);
        $encodeData = (($encodeData) << 7) | ($length);
        $encodeData = pack('n', $encodeData);

        switch ($length) {
            case 126: $encodeData .= pack('n*', $extn); break;
            case 127: $encodeData .= pack('NN', ($extn >> 32), ($extn & 0xFFFFFFFF)); break;
        }

        if ($mask == 1) {
            $maskkey = $this->getMaskKey();
            $encodeData .= $maskkey;
            $rawMessage = $this->maskData($rawMessage, $maskkey);
        }

        return $encodeData.$rawMessage;
    }

    public function decodePayload()
    {
        $payload = $this->getPayload();
		if (null === $payload) return;

        $payload = array_map('ord', str_split($payload));

        $fin    = (($payload[0]) >> 7);
        $rsv1   = (($payload[0]) >> 6) & 0x1;
        $rsv2   = (($payload[0]) >> 5) & 0x1;
        $rsv3   = (($payload[0]) >> 4) & 0x1;
        $opcode = ($payload[0]) & 0xF;
        $mask   = (($payload[1]) >> 7);
        $length = ($payload[1]) & 0x7F;
        $maskkey = "\x00\x00\x00\x00";

        $this->setFin($fin);
        $this->setRsv1($rsv1);
        $this->setRsv2($rsv2);
        $this->setRsv3($rsv3);
        $this->setOpcode($opcode);
        $this->setMask($mask);

        if ($length < 3) return false;

        $payload = implode('', array_map('chr', $payload));
        $payloadOffset = 2;
        switch ($length)
        {
            case 126:
                $payloadOffset = 4;
                $length = unpack('H*', substr($payload, 2, 2));
                $length = hexdec($length[1]);
                break;
            case 127:
                $payloadOffset = 6;
                $length = unpack('H*', substr($payload, 2, 4));
                $length = hexdec($length[1]);
                break;
        }

        if ($mask == 1)
        {
            $maskkey = substr($payload, $payloadOffset, 4);
            $this->setMaskKey($maskkey);

            $payloadOffset += 4;
        }

        $data = substr($payload, $payloadOffset, $length);
        if ($mask == 1) $data = $this->maskData($data, $maskkey);

        return $data;
    }

    public function maskData($data, $key) {
        $masked = '';

        for ($i = 0; $i < strlen($data); $i++) {
            $masked .= $data[$i] ^ $key[$i % 4];
        }

        return $masked;
    }
}
