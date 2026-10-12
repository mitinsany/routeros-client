<?php

namespace MitinSany\RouterOS\Tests\Protocol;

use MitinSany\RouterOS\Protocol\SentenceWords;
use PHPUnit\Framework\TestCase;

class SentenceWordsTest extends TestCase
{
    public function testCommandPreservesAttributeAndQueryOrderAndBinaryValues()
    {
        $this->assertSame(
            ['/queue/simple/print', '=.proplist=.id,name', '=comment=a=b', '=disabled=', "=data=\xFF",
                '?comment=sync', '?name=client', '?#|!'],
            SentenceWords::command(
                '/queue/simple/print',
                ['.proplist' => '.id,name', 'comment' => 'a=b', 'disabled' => false, 'data' => "\xFF"],
                ['?comment=sync', '?name=client', '?#|!']
            )
        );
    }

    public function testAttributesKeepEqualsEmptyValuesAndLastDuplicate()
    {
        $words = ['!re', '=name=first', '=comment=a=b', '=name=last', '=empty=', '=broken', '.tag=one',
            '=.tag=two', 42];

        $this->assertSame(
            ['name' => 'last', 'comment' => 'a=b', 'empty' => '', '.tag' => 'two'],
            SentenceWords::attributes($words)
        );
        $this->assertSame(
            ['name' => 'last', 'comment' => 'a=b', 'empty' => ''],
            SentenceWords::attributes($words, true)
        );
    }

    public function testTagSupportsBothLegacyFormsAndKeepsFirstTag()
    {
        $this->assertSame('first', SentenceWords::tag(['!re', '.tag=first', '=.tag=second']));
        $this->assertSame('first', SentenceWords::tag(['!re', '=.tag=first', '.tag=second']));
        $this->assertFalse(SentenceWords::tag(['!done']));
        $this->assertSame('!done', SentenceWords::type(['!done']));
        $this->assertFalse(SentenceWords::type([]));
    }
}
