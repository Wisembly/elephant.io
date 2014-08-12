<?php
/**
 * This file is part of the Elephant.io package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

namespace ElephantIO\Payload;

use ElephantIO\AbstractPayload;

/**
 * Encode the payload before sending it to a frame
 *
 * Based on the work of the following :
 *   - Ludovic Barreca (@ludovicbarreca), project founder
 *   - Byeoung Wook (@kbu1564) in #49
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
class Encoder extends AbstractPayload
{
    private $data;
    private $payload;

    /**
     * @param string  $data   data to encode
     * @param integer $opcode OpCode to use (one of AbstractPayload's constant)
     * @param bool    $mask   Should we use a mask ?
     */
    public function __construct($data, $opCode, $mask)
    {
        $this->data    = $data;
        $this->opCode  = $opCode;
        $this->mask    = (bool) $mask;

        if (true === $this->mask) {
            $this->maskKey = openssl_random_pseudo_bytes(4);
        }
    }

    public function encode()
    {
        if (null !== $this->payload) {
            return;
        }

        $pack   = '';
        $length = strlen($this->data);

        if (0xFFFF < $length) {
            $pack   = pack('NN', ($length & 0xFFFFFFFF00000000) >> 0b100000, $length & 0x00000000FFFFFFFF);
            $length = 0x007F;
        } elseif (0x007D < $length) {
            $pack   = pack('n*', $length);
            $length = 0x007E;
        }

        $payload = ($this->fin << 0b001) | $this->rsv[0];
        $payload = ($payload   << 0b001) | $this->rsv[1];
        $payload = ($payload   << 0b001) | $this->rsv[2];
        $payload = ($payload   << 0b100) | $this->opCode;
        $payload = ($payload   << 0b001) | $this->mask;
        $payload = ($payload   << 0b111) | $length;

        $data    = $this->data;
        $payload = pack('n', $payload) . $pack;

        if (true === $this->mask) {
            $payload .= $this->maskKey;
            $data     = $this->maskData($data);
        }

        $this->payload = $payload . $data;
    }

    public function __toString()
    {
        $this->encode();

        return $this->payload;
    }
}

