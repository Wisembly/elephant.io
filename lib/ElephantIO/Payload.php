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

    /**
     * Sets all variables in one method
     * @param {string} $var
     * @param {mixed} $value
     * @return {Payload}
     */
    public function __set($var,$value){
        if($var == 'mask' && $value === true)
            $value = $this->_generateMaskKey();
        $this->{$var} = $value;
    }

    /**
     * Gets a variable value
     * @param {string} $var Var name
     * @return {mixed} Var value
     */
    public function __get($var){
        $value = $this->{$var};
        if($var == 'length')
            $value = strlen($this->payload);
        return $value;
    }

    /**
     * Generates mark key
     * @return {string}
     */
    private function _generateMaskKey() {
        return $key = openssl_random_pseudo_bytes(4);
    }

    public function encodePayload()
    {
        $payload = (($this->fin) << 1) | ($this->rsv1);
        $payload = (($payload) << 1) | ($this->rsv2);
        $payload = (($payload) << 1) | ($this->rsv3);
        $payload = (($payload) << 4) | ($this->opcode);
        $payload = (($payload) << 1) | ($this->mask);

        if ($this->length <= 125) {
            $payload = (($payload) << 7) | ($this->length);
            $payload = pack('n', $payload);
        } elseif ($this->length <= 0xffff) {
            $payload = (($payload) << 7) | 126;
            $payload = pack('n', $payload).pack('n*', $this->length);
        } else {
            $payload = (($payload) << 7) | 127;
            $left = 0xffffffff00000000;
            $right = 0x00000000ffffffff;
            $l = ($this->length & $left) >> 32;
            $r = $this->length & $right;
            $payload = pack('n', $payload).pack('NN', $l, $r);
        }

        if ($this->mask == 0x1) {
            $payload .= $this->maskKey;
            $data = $this->maskData($this->payload, $this->maskKey);
        } else {
            $data = $this->payload;
        }

        $payload = $payload.$data;

        return $payload;
    }

    public function maskData($data, $key) {
        $masked = '';

        for ($i = 0; $i < strlen($data); $i++) {
            $masked .= $data[$i] ^ $key[$i % 4];
        }

        return $masked;
    }
}
