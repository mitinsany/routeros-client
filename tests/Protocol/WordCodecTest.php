<?php

namespace MitinSany\RouterOS\Tests\Protocol;

use MitinSany\RouterOS\Protocol\WordCodec;
use PHPUnit\Framework\TestCase;

class WordCodecTest extends TestCase
{
    /**
     * @dataProvider lengthEncodingProvider
     */
    public function testEncodeLengthUsesRouterOsBoundaryPrefixes($length, $expected)
    {
        $this->assertSame($expected, WordCodec::encodeLength($length));
    }

    public function lengthEncodingProvider()
    {
        return [
            [0x00, "\x00"],
            [0x7f, "\x7f"],
            [0x80, "\x80\x80"],
            [0x3fff, "\xbf\xff"],
            [0x4000, "\xc0\x40\x00"],
            [0x1fffff, "\xdf\xff\xff"],
            [0x200000, "\xe0\x20\x00\x00"],
            [0x0fffffff, "\xef\xff\xff\xff"],
            [0x10000000, "\xf0\x10\x00\x00\x00"]
        ];
    }

    public function testEncodeWordKeepsBinaryPayloadUnchanged()
    {
        $this->assertSame("\x04a=\x00x", WordCodec::encodeWord("a=\x00x"));
    }

    public function testDecodeLengthReadsRemainingPrefixBytesFromReader()
    {
        $reader = function ($length) {
            $this->assertSame(2, $length);

            return "\x40\x00";
        };

        $this->assertSame(0x4000, WordCodec::decodeLength("\xc0", $reader));
    }

    public function testDecodeWordReadsItsBinaryPayloadFromReader()
    {
        $parts = ["\x04", "a=\x00x"];
        $reader = function ($length) use (&$parts) {
            $this->assertSame(strlen($parts[0]), $length);

            return array_shift($parts);
        };

        $this->assertSame("a=\x00x", WordCodec::decodeWord($reader));
    }
}
