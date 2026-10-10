<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests;

use MitinSany\RouterOS\ConfigurationSynchronizer;
use MitinSany\RouterOS\Tests\Support\SynchronizingRouter;
use PHPUnit\Framework\TestCase;

class ConfigurationSynchronizerReconciliationTest extends TestCase
{
    public function testSectionWithoutReadOptionsUsesLegacyGetAllCall()
    {
        $sync = $this->queueSynchronizer();
        $router = $this->routerWith('/queue/simple', []);

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            [['getAll', '/queue/simple', false, [], false, false, []]],
            $router->records
        );
    }

    public function testReadOptionsPassProplistAndOrderedQueryWords()
    {
        $sync = $this->queueSynchronizer();
        $this->assertTrue(method_exists($sync, 'readOptions'));
        $sync->readOptions(
            'queues',
            ['.id', 'name', 'comment', 'disabled', 'max-limit'],
            ['?comment=sync', '?disabled=no', '?#|!']
        );
        $router = $this->routerWith('/queue/simple', []);

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            [[
                'getAll',
                '/queue/simple',
                ['.id', 'name', 'comment', 'disabled', 'max-limit'],
                [],
                false,
                false,
                ['?comment=sync', '?disabled=no', '?#|!']
            ]],
            $router->records
        );
    }

    public function testUnchangedManagedConfigurationDoesNotMutateRouter()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', ['name' => 'client-1', 'target' => '192.0.2.10/32']);
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*1', 'name' => 'client-1', 'target' => '192.0.2.10/32', 'comment' => 'sync']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame([], $router->mutatingRecords());
    }

    public function testChangedMatchingEntryUsesSetWithId()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('/queue/simple', ['name' => 'client-1', 'target' => '192.0.2.11/32']);
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*1', 'name' => 'client-1', 'target' => '192.0.2.10/32', 'comment' => 'sync']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            [['set', '/queue/simple', ['target' => '192.0.2.11/32', '.id' => '*1']]],
            $router->mutatingRecords()
        );
    }

    public function testMissingEntryIsAddedWithDefaults()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', ['name' => 'client-1', 'target' => '192.0.2.10/32']);
        $router = $this->routerWith('/queue/simple', []);

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            [[
                'add',
                '/queue/simple',
                ['comment' => 'sync', 'name' => 'client-1', 'target' => '192.0.2.10/32']
            ]],
            $router->mutatingRecords()
        );
    }

    public function testStaleManagedEntryIsRemovedButManualEntryIsPreserved()
    {
        $sync = $this->queueSynchronizer();
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*managed', 'name' => 'old', 'comment' => 'sync'],
            ['.id' => '*manual', 'name' => 'manual', 'comment' => 'manual']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame([['remove', '/queue/simple', '*managed']], $router->mutatingRecords());
    }

    public function testIgnoreRuleProtectsEntryFromUpdateAndRemoval()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', ['name' => 'client-1', 'target' => '192.0.2.11/32']);
        $sync->ignore('/queue/simple', ['name' => 'client-1']);
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*1', 'name' => 'client-1', 'target' => '192.0.2.10/32', 'comment' => 'sync']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame([], $router->mutatingRecords());
        $this->assertContains('ignore /queue/simple *1', $sync->logs);
    }

    public function testDefaultAndUnmatchedDynamicEntriesAreNeverRemoved()
    {
        $sync = $this->queueSynchronizer();
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*default', 'name' => 'default', 'comment' => 'sync', 'default' => 'true'],
            ['.id' => '*dynamic', 'name' => 'dynamic', 'comment' => 'sync', 'dynamic' => 'true']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame([], $router->mutatingRecords());
    }

    public function testMatchingDynamicEntryIsRemovedBeforeStaticEntryIsAdded()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', ['name' => 'client-1', 'target' => '192.0.2.10/32']);
        $router = $this->routerWith('/queue/simple', [
            [
                '.id' => '*dynamic', 'name' => 'client-1', 'target' => '192.0.2.10/32',
                'comment' => 'sync', 'dynamic' => 'true'
            ]
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            [
                ['remove', '/queue/simple', '*dynamic'],
                [
                    'add',
                    '/queue/simple',
                    ['comment' => 'sync', 'name' => 'client-1', 'target' => '192.0.2.10/32']
                ]
            ],
            $router->mutatingRecords()
        );
    }

    public function testOrderedSectionMovesOnlyAnOutOfOrderExistingEntry()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('rules', '/ip/firewall/filter', 'addset_order', 'name', ['comment' => 'sync']);
        $sync->pass('rules', ['comment' => 'sync']);
        $sync->config('rules', ['name' => 'first']);
        $sync->config('rules', ['name' => 'second']);
        $router = $this->routerWith('/ip/firewall/filter', [
            ['.id' => '*2', 'name' => 'second', 'comment' => 'sync'],
            ['.id' => '*1', 'name' => 'first', 'comment' => 'sync']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame([['move', '/ip/firewall/filter', '*1', '*2']], $router->mutatingRecords());
    }

    public function testRouterOperationFailureStopsSynchronization()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', ['name' => 'client-1', 'target' => '192.0.2.11/32']);
        $sync->config('queues', ['name' => 'client-2', 'target' => '192.0.2.12/32']);
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*1', 'name' => 'client-1', 'target' => '192.0.2.10/32', 'comment' => 'sync']
        ]);
        $router->failures['set'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertSame(
            [['set', '/queue/simple', ['target' => '192.0.2.11/32', '.id' => '*1']]],
            $router->mutatingRecords()
        );
        $this->assertContains('set failed /queue/simple *1', $sync->logs);
    }

    public function testGetAllFailureStopsSynchronization()
    {
        $sync = $this->queueSynchronizer();
        $router = $this->routerWith('/queue/simple', []);
        $router->failures['getAll'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertContains('getAll failed /queue/simple', $sync->logs);
    }

    public function testAddFailureStopsSynchronization()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', ['name' => 'client-1']);
        $router = $this->routerWith('/queue/simple', []);
        $router->failures['add'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertContains('add failed /queue/simple', $sync->logs);
    }

    public function testRemoveFailureStopsSynchronization()
    {
        $sync = $this->queueSynchronizer();
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*old', 'name' => 'old', 'comment' => 'sync']
        ]);
        $router->failures['remove'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertContains('remove failed /queue/simple *old', $sync->logs);
    }

    public function testMoveFailureStopsSynchronization()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('rules', '/ip/firewall/filter', 'addset_order', 'name', ['comment' => 'sync']);
        $sync->pass('rules', ['comment' => 'sync']);
        $sync->config('rules', ['name' => 'first']);
        $sync->config('rules', ['name' => 'second']);
        $router = $this->routerWith('/ip/firewall/filter', [
            ['.id' => '*2', 'name' => 'second', 'comment' => 'sync'],
            ['.id' => '*1', 'name' => 'first', 'comment' => 'sync']
        ]);
        $router->failures['move'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertContains('move failed /ip/firewall/filter *1', $sync->logs);
    }

    public function testSetSectionNeverAddsOrRemovesEntries()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('queues', '/queue/simple', 'set', 'name', ['comment' => 'sync']);
        $sync->config('queues', ['name' => 'existing', 'target' => '192.0.2.11/32']);
        $sync->config('queues', ['name' => 'missing', 'target' => '192.0.2.12/32']);
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*1', 'name' => 'existing', 'target' => '192.0.2.10/32', 'comment' => 'sync'],
            ['.id' => '*old', 'name' => 'old', 'comment' => 'sync']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            [['set', '/queue/simple', ['target' => '192.0.2.11/32', '.id' => '*1']]],
            $router->mutatingRecords()
        );
    }

    public function testDuplicateKeysAreMatchedInConfigurationOrder()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', ['name' => 'client', 'target' => '192.0.2.10/32']);
        $sync->config('queues', ['name' => 'client', 'target' => '192.0.2.11/32']);
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*1', 'name' => 'client', 'target' => '192.0.2.10/32', 'comment' => 'sync'],
            ['.id' => '*2', 'name' => 'client', 'target' => '192.0.2.11/32', 'comment' => 'sync']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame([], $router->mutatingRecords());
    }

    public function testBinaryKeyValuesDoNotRequireJsonForMatching()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', ['name' => "\xB1", 'target' => '192.0.2.10/32']);
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*1', 'name' => "\xB1", 'target' => '192.0.2.10/32', 'comment' => 'sync']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame([], $router->mutatingRecords());
    }

    public function testIndexedMatchingPreservesLegacyFalseAndEmptyKeyEquivalence()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', ['name' => '', 'target' => '192.0.2.10/32']);
        $router = $this->routerWith('/queue/simple', [
            ['.id' => '*1', 'name' => 'false', 'target' => '192.0.2.10/32', 'comment' => 'sync']
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame([], $router->mutatingRecords());
    }

    public function testValueSectionSetsOnlyDifferingFieldsAndHonorsLegacyEquivalence()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('identity', '/system/identity', 'value');
        $sync->config('identity', ['name' => 'router', 'disabled' => false, 'comment' => '', 'list' => 'a,b']);
        $router = $this->routerWith('/system/identity', [
            'name' => 'router',
            'list' => 'a;b'
        ]);

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            [['set', '/system/identity', ['disabled' => false, 'comment' => '']]],
            $router->mutatingRecords()
        );
    }

    private function queueSynchronizer()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('queues', '/queue/simple', 'addset', 'name', ['comment' => 'sync']);
        $sync->pass('queues', ['comment' => 'sync']);

        return $sync;
    }

    private function routerWith($path, $records)
    {
        $router = new SynchronizingRouter();
        $router->responses[$path] = $records;

        return $router;
    }
}
