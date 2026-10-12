<?php

namespace MitinSany\RouterOS\Tests\Synchronization;

use MitinSany\RouterOS\Synchronization\DesiredRecordIndex;
use PHPUnit\Framework\TestCase;

class DesiredRecordIndexTest extends TestCase
{
    public function testDuplicatesRespectMatchedIndexesAndCanBeLookedUpWithoutConsuming()
    {
        $index = new DesiredRecordIndex([['name' => 'same'], ['name' => 'same']], ['name']);

        $this->assertSame(0, $index->find(['name' => 'same']));
        $this->assertSame(0, $index->find(['name' => 'same']));
        $index->markMatched(0);
        $this->assertSame(1, $index->find(['name' => 'same']));
        $index->markMatched(1);
        $this->assertFalse($index->find(['name' => 'same']));
    }

    public function testExactNormalizedKeyHasPriorityOverLegacyFallback()
    {
        $index = new DesiredRecordIndex([['name' => false], ['name' => '']], ['name']);

        $this->assertSame(1, $index->find(['name' => '']));
        $index->markMatched(1);
        $this->assertSame(0, $index->find(['name' => '']));
    }

    public function testMissingKeysDoNotMatchAndCompositeKeysPreserveBinaryValues()
    {
        $index = new DesiredRecordIndex(
            [['name' => "\xFF", 'target' => 'a;b'], ['name' => "\xFF"]],
            ['name', 'target']
        );

        $this->assertSame(0, $index->find(['name' => "\xFF", 'target' => 'a,b']));
        $this->assertFalse($index->find(['name' => "\xFF"]));
        $empty = new DesiredRecordIndex([['name' => 'same']], []);
        $this->assertFalse($empty->find(['name' => 'same']));
    }

    public function testDuplicateQueuePreservesOrderAfterOutOfOrderAndRepeatedMarks()
    {
        $index = new DesiredRecordIndex([['name' => 'same'], ['name' => 'same'], ['name' => 'same']], ['name']);
        $index->markMatched(1);
        $index->markMatched(1);
        $this->assertSame(0, $index->find(['name' => 'same']));
        $index->markMatched(0);
        $this->assertSame(2, $index->find(['name' => 'same']));
        $index->markMatched(2);
        $this->assertFalse($index->find(['name' => 'same']));
    }

    public function testLargeDuplicateQueueReturnsEveryDesiredRecordOnce()
    {
        $desired = array_fill(0, 5000, ['name' => 'same']);
        $index = new DesiredRecordIndex($desired, ['name']);
        for ($position = 0; $position < count($desired); $position++) {
            $this->assertSame($position, $index->find(['name' => 'same']));
            $index->markMatched($position);
        }
        $this->assertFalse($index->find(['name' => 'same']));
    }
}
