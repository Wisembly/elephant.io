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
                ['?EV!', '81833f455621592a39']];
    }

    /**
     * Test with a payload > 125 characters but < 65536
     *
     * @dataProvider providerLongPayload
     */
    public function testLongPayload($maskKey, $expected)
    {
        $payload = <<<'PAYLOAD'
This payload length is over 125 chars, hence the length part inside the payload
should now be 16 bits in length. There are still a little bit less than that to
satisfy the fact that we need more than 125 characters, but less than 65536. So
this should do the trick...
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
        $noMask   = '817e010b54686973207061796c6f6164206c656e677468206973206f76'
                  . '6572203132352063686172732c2068656e636520746865206c656e6774'
                  . '68207061727420696e7369646520746865207061796c6f61640a73686f'
                  . '756c64206e6f77206265203136206269747320696e206c656e6774682e'
                  . '20546865726520617265207374696c6c2061206c6974746c6520626974'
                  . '206c657373207468616e207468617420746f0a73617469736679207468'
                  . '6520666163742074686174207765206e656564206d6f7265207468616e'
                  . '2031323520636861726163746572732c20627574206c65737320746861'
                  . '6e2036353533362e20536f0a746869732073686f756c6420646f207468'
                  . '6520747269636b2e2e2e';

        $withMask = '81fe010b3f4556216b2d3f521f353758532a37451f29334f58313e0156'
                  . '36764e492024010e7763015c2d37534c6976495a2b35441f313e441f29'
                  . '334f58313e014f2424551f2c3852562133014b2d33014f242f4d502432'
                  . '2b4c2d39545321764f503276435a6567171f273f554c653f4f1f29334f'
                  . '58313e0f1f113e444d2076404d2076524b2c3a4d1f24764d5631224d5a'
                  . '6534484b653a444c367655572438014b2d37551f31392b4c2422484c23'
                  . '2f014b2d3301592435551f313e404b6521441f2b33445b653b4e4d2076'
                  . '55572438010e7763015c2d37535e2622444d367a015d30220153202552'
                  . '1f313e40516560140a76600f1f16392b4b2d3f521f363e4e4a2932015b'
                  . '2a7655572076554d2c354a116b78';

        return [[null, $noMask],
                ['?EV!', $withMask]];
    }
}

