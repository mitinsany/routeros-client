<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests\Synchronization;

use MitinSany\RouterOS\Synchronization\DesiredRecordIndex;
use PHPUnit\Framework\TestCase;

class DesiredRecordIndexTest extends TestCase
{
    public function testDuplicatesRespectMatchedIndexesAndCanBeLookedUpWithoutConsuming()
    {
        $index = new DesiredRecordIndex([['name' => 'same'], ['name' => 'same']], ['name']);

        $this->assertSame(0, $index->find(['name' => 'same'], []));
        $this->assertSame(0, $index->find(['name' => 'same'], []));
        $this->assertSame(1, $index->find(['name' => 'same'], [0 => '*1']));
        $this->assertFalse($index->find(['name' => 'same'], [0 => '*1', 1 => '*2']));
    }

    public function testExactNormalizedKeyHasPriorityOverLegacyFallback()
    {
        $index = new DesiredRecordIndex([['name' => false], ['name' => '']], ['name']);

        $this->assertSame(1, $index->find(['name' => ''], []));
        $this->assertSame(0, $index->find(['name' => ''], [1 => '*1']));
    }

    public function testMissingKeysDoNotMatchAndCompositeKeysPreserveBinaryValues()
    {
        $index = new DesiredRecordIndex(
            [['name' => "\xFF", 'target' => 'a;b'], ['name' => "\xFF"]],
            ['name', 'target']
        );

        $this->assertSame(0, $index->find(['name' => "\xFF", 'target' => 'a,b'], []));
        $this->assertFalse($index->find(['name' => "\xFF"], []));
        $empty = new DesiredRecordIndex([['name' => 'same']], []);
        $this->assertFalse($empty->find(['name' => 'same'], []));
    }
}
