<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests\Synchronization;

use MitinSany\RouterOS\Synchronization\ValueComparison;
use PHPUnit\Framework\TestCase;

class ValueComparisonTest extends TestCase
{
    /** @dataProvider equivalentValues */
    public function testPreservesLegacyValueEquivalence($left, $right, $expected)
    {
        $this->assertSame($expected, ValueComparison::equivalent($left, $right));
        $this->assertSame($expected, ValueComparison::equivalent($right, $left));
    }

    public function equivalentValues()
    {
        return [
            ['a;b', 'a,b', true],
            [false, '', true],
            ['no', '', true],
            [null, false, true],
            [false, 'no', false],
            [true, 'true', true],
            [10, '10', true],
            ['a,b', 'b,a', false],
            ["\xFF", "\xFF", true]
        ];
    }

    public function testDiffIncludesExplicitEmptyValuesWhenAttributeIsMissing()
    {
        $this->assertSame(
            ['disabled' => false, 'comment' => ''],
            ValueComparison::changes(['target' => 'a,b'], ['target' => 'a;b', 'disabled' => false, 'comment' => ''])
        );
    }
}
