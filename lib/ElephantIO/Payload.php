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
    private $mask = 0x1;
    private $length;
    private $maskKey;
    private $payload;

    public function setFin($fin)
    {
        $this->fin = $fin;

        return $this;
    }

    public function getFin()
    {
        return $this->fin;
    }

    public function setRsv1($rsv1)
    {
        $this->rsv1 = $rsv1;

        return $this;
    }

    public function getRsv1()
    {
        return $this->rsv1;
    }

    public function setRsv2($rsv2)
    {
        $this->rsv2 = $rsv2;

        return $this;
    }

    public function getRsv2()
    {
        return $this->rsv2;
    }

    public function setRsv3($rsv3)
    {
        $this->rsv3 = $rsv3;

        return $this;
    }

    public function getRsv3()
    {
        return $this->rsv3;
    }

    public function setOpcode($opcode)
    {
        $this->opcode = $opcode;

        return $this;
    }

    public function getOpcode()
    {
        return $this->opcode;
    }

    public function setMask($mask)
    {
        $this->mask = $mask;

        return $this;
    }

    public function getMask()
    {
        return $this->mask;
    }

    public function setLength($length)
    {
        $this->length = $length;

        return $this;
    }

    public function getLength()
    {
        return $this->length;
    }

    public function setMaskKey($maskKey)
    {
        $this->maskKey = $maskKey;

        return $this;
    }

    public function getMaskKey()
    {
        return $this->maskKey;
    }

    public function setPayload($payload)
    {
        $this->payload = $payload;

        return $this;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function encodePayload()
    {
        $payload = (($this->getFin()) << 1) | ($this->getRsv1());
        $payload = (($payload) << 1) | ($this->getRsv2());
        $payload = (($payload) << 1) | ($this->getRsv3());
        $payload = (($payload) << 4) | ($this->getOpcode());
        $payload = (($payload) << 1) | ($this->getMask());
        $payload = (($payload) << 7) | ($this->getLength());
        $payload = (($payload) << 32) | ($this->getMaskKey());
        $payload = dechex($payload);
        $payload = $payload.$this->getPayload();

        return $payload;
    }
}
