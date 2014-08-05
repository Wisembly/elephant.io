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

use ReflectionProperty;

use PHPUnit_Framework_TestCase;

class EncoderTest extends PHPUnit_Framework_TestCase
{
    /** @dataProvider providerShortPayload */
    public function testShortPayload($maskKey, $expected)
    {
        $encoder = new Encoder('foo', Encoder::OPCODE_TEXT, null !== $maskKey);

        if (null !== $maskKey) {
            $refl = new ReflectionProperty('ElephantIO\\AbstractPayload', 'maskKey');
            $refl->setAccessible(true);
            $refl->setValue($encoder, $maskKey);
        }

        $this->assertSame($expected, bin2hex((string) $encoder));
    }

    public function providerShortPayload()
    {
        return [[null, '8103666f6f'],
                ['b4r', '8183666f6f']];
    }

    /** @dataProvider providerLongPayload */
    public function testLongPayload($maskKey, $expected)
    {
        $payload = <<<'PAYLOAD'
This payload length is over 125 chars, hence the length part inside the payload
should now be 16 bits in length. Still 8 to go
PAYLOAD
;

        $encoder = new Encoder($payload, Encoder::OPCODE_TEXT, null !== $maskKey);

        if (null !== $maskKey) {
            $refl = new ReflectionProperty('ElephantIO\\AbstractPayload', 'maskKey');
            $refl->setAccessible(true);
            $refl->setValue($encoder, $maskKey);
        }

        $this->assertSame($expected, bin2hex((string) $encoder));
    }

    public function providerLongPayload()
    {
        $noMask   = '817f000000000000007e54686973207061796c6f6164206c656e677468'
                  . '206973206f766572203132352063686172732c2068656e636520746865'
                  . '206c656e677468207061727420696e7369646520746865207061796c6f'
                  . '61640a73686f756c64206e6f77206265203136206269747320696e206c'
                  . '656e6774682e205374696c6c203820746f20676f';

        $withMask = '81ff000000000000007e54686973207061796c6f6164206c656e677468'
                  . '206973206f766572203132352063686172732c2068656e636520746865'
                  . '206c656e677468207061727420696e7369646520746865207061796c6f'
                  . '61640a73686f756c64206e6f77206265203136206269747320696e206c'
                  . '656e6774682e205374696c6c203820746f20676f';

        return [[null, $noMask],
                ['b4r', $withMask]];
    }

    /** @dataProvider providerWiderPayload */
    public function testWiderPayload($maskKey, $expected)
    {
        $payload = <<<'PAYLOAD'
This payload length is over 127 chars, hence the length part inside the payload
should now be 16 bits in length. Still more than 10 to go, as here we got waaay
over 127 characters.
PAYLOAD
;

        $encoder = new Encoder($payload, Encoder::OPCODE_TEXT, null !== $maskKey);

        if (null !== $maskKey) {
            $refl = new ReflectionProperty('ElephantIO\\AbstractPayload', 'maskKey');
            $refl->setAccessible(true);
            $refl->setValue($encoder, $maskKey);
        }

        $this->assertSame($expected, bin2hex((string) $encoder));
    }

    public function providerWiderPayload()
    {
        $noMask   = '817f00000000000000b454686973207061796c6f6164206c656e677468'
                  . '206973206f766572203132372063686172732c2068656e636520746865'
                  . '206c656e677468207061727420696e7369646520746865207061796c6f'
                  . '61640a73686f756c64206e6f77206265203136206269747320696e206c'
                  . '656e6774682e205374696c6c206d6f7265207468616e20313020746f20'
                  . '676f2c206173206865726520776520676f742077616161790a6f766572'
                  . '2031323720636861726163746572732e';

        $withMask = '81ff00000000000000b454686973207061796c6f6164206c656e677468'
                  . '206973206f766572203132372063686172732c2068656e636520746865'
                  . '206c656e677468207061727420696e7369646520746865207061796c6f'
                  . '61640a73686f756c64206e6f77206265203136206269747320696e206c'
                  . '656e6774682e205374696c6c206d6f7265207468616e20313020746f20'
                  . '676f2c206173206865726520776520676f742077616161790a6f766572'
                  . '2031323720636861726163746572732e';

        return [[null, $noMask],
                ['b4r', $withMask]];
    }

}

