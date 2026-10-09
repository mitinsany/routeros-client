<?php

namespace MitinSany\RouterOS\Tests;

use MitinSany\RouterOS\ConfigurationSynchronizer;
use MitinSany\RouterOS\Tests\Support\SynchronizingRouter;
use PHPUnit\Framework\TestCase;

class ConfigurationSynchronizerReconciliationTest extends TestCase
{
    public function testUnchangedManagedConfigurationDoesNotMutateRouter()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', array('name' => 'client-1', 'target' => '192.0.2.10/32'));
        $router = $this->routerWith('/queue/simple', array(
            array('.id' => '*1', 'name' => 'client-1', 'target' => '192.0.2.10/32', 'comment' => 'sync')
        ));

        $this->assertTrue($sync->update($router));
        $this->assertSame(array(), $router->mutatingRecords());
    }

    public function testChangedMatchingEntryUsesSetWithId()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('/queue/simple', array('name' => 'client-1', 'target' => '192.0.2.11/32'));
        $router = $this->routerWith('/queue/simple', array(
            array('.id' => '*1', 'name' => 'client-1', 'target' => '192.0.2.10/32', 'comment' => 'sync')
        ));

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            array(array('set', '/queue/simple', array('target' => '192.0.2.11/32', '.id' => '*1'))),
            $router->mutatingRecords()
        );
    }

    public function testMissingEntryIsAddedWithDefaults()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', array('name' => 'client-1', 'target' => '192.0.2.10/32'));
        $router = $this->routerWith('/queue/simple', array());

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            array(array(
                'add',
                '/queue/simple',
                array('comment' => 'sync', 'name' => 'client-1', 'target' => '192.0.2.10/32')
            )),
            $router->mutatingRecords()
        );
    }

    public function testStaleManagedEntryIsRemovedButManualEntryIsPreserved()
    {
        $sync = $this->queueSynchronizer();
        $router = $this->routerWith('/queue/simple', array(
            array('.id' => '*managed', 'name' => 'old', 'comment' => 'sync'),
            array('.id' => '*manual', 'name' => 'manual', 'comment' => 'manual')
        ));

        $this->assertTrue($sync->update($router));
        $this->assertSame(array(array('remove', '/queue/simple', '*managed')), $router->mutatingRecords());
    }

    public function testIgnoreRuleProtectsEntryFromUpdateAndRemoval()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', array('name' => 'client-1', 'target' => '192.0.2.11/32'));
        $sync->ignore('/queue/simple', array('name' => 'client-1'));
        $router = $this->routerWith('/queue/simple', array(
            array('.id' => '*1', 'name' => 'client-1', 'target' => '192.0.2.10/32', 'comment' => 'sync')
        ));

        $this->assertTrue($sync->update($router));
        $this->assertSame(array(), $router->mutatingRecords());
        $this->assertContains('ignore /queue/simple *1', $sync->logs);
    }

    public function testDefaultAndUnmatchedDynamicEntriesAreNeverRemoved()
    {
        $sync = $this->queueSynchronizer();
        $router = $this->routerWith('/queue/simple', array(
            array('.id' => '*default', 'name' => 'default', 'comment' => 'sync', 'default' => 'true'),
            array('.id' => '*dynamic', 'name' => 'dynamic', 'comment' => 'sync', 'dynamic' => 'true')
        ));

        $this->assertTrue($sync->update($router));
        $this->assertSame(array(), $router->mutatingRecords());
    }

    public function testMatchingDynamicEntryIsRemovedBeforeStaticEntryIsAdded()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', array('name' => 'client-1', 'target' => '192.0.2.10/32'));
        $router = $this->routerWith('/queue/simple', array(
            array(
                '.id' => '*dynamic', 'name' => 'client-1', 'target' => '192.0.2.10/32',
                'comment' => 'sync', 'dynamic' => 'true'
            )
        ));

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            array(
                array('remove', '/queue/simple', '*dynamic'),
                array(
                    'add',
                    '/queue/simple',
                    array('comment' => 'sync', 'name' => 'client-1', 'target' => '192.0.2.10/32')
                )
            ),
            $router->mutatingRecords()
        );
    }

    public function testOrderedSectionMovesOnlyAnOutOfOrderExistingEntry()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('rules', '/ip/firewall/filter', 'addset_order', 'name', array('comment' => 'sync'));
        $sync->pass('rules', array('comment' => 'sync'));
        $sync->config('rules', array('name' => 'first'));
        $sync->config('rules', array('name' => 'second'));
        $router = $this->routerWith('/ip/firewall/filter', array(
            array('.id' => '*2', 'name' => 'second', 'comment' => 'sync'),
            array('.id' => '*1', 'name' => 'first', 'comment' => 'sync')
        ));

        $this->assertTrue($sync->update($router));
        $this->assertSame(array(array('move', '/ip/firewall/filter', '*1', '*2')), $router->mutatingRecords());
    }

    public function testRouterOperationFailureStopsSynchronization()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', array('name' => 'client-1', 'target' => '192.0.2.11/32'));
        $sync->config('queues', array('name' => 'client-2', 'target' => '192.0.2.12/32'));
        $router = $this->routerWith('/queue/simple', array(
            array('.id' => '*1', 'name' => 'client-1', 'target' => '192.0.2.10/32', 'comment' => 'sync')
        ));
        $router->failures['set'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertSame(
            array(array('set', '/queue/simple', array('target' => '192.0.2.11/32', '.id' => '*1'))),
            $router->mutatingRecords()
        );
        $this->assertContains('set failed /queue/simple *1', $sync->logs);
    }

    public function testGetAllFailureStopsSynchronization()
    {
        $sync = $this->queueSynchronizer();
        $router = $this->routerWith('/queue/simple', array());
        $router->failures['getAll'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertContains('getAll failed /queue/simple', $sync->logs);
    }

    public function testAddFailureStopsSynchronization()
    {
        $sync = $this->queueSynchronizer();
        $sync->config('queues', array('name' => 'client-1'));
        $router = $this->routerWith('/queue/simple', array());
        $router->failures['add'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertContains('add failed /queue/simple', $sync->logs);
    }

    public function testRemoveFailureStopsSynchronization()
    {
        $sync = $this->queueSynchronizer();
        $router = $this->routerWith('/queue/simple', array(
            array('.id' => '*old', 'name' => 'old', 'comment' => 'sync')
        ));
        $router->failures['remove'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertContains('remove failed /queue/simple *old', $sync->logs);
    }

    public function testMoveFailureStopsSynchronization()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('rules', '/ip/firewall/filter', 'addset_order', 'name', array('comment' => 'sync'));
        $sync->pass('rules', array('comment' => 'sync'));
        $sync->config('rules', array('name' => 'first'));
        $sync->config('rules', array('name' => 'second'));
        $router = $this->routerWith('/ip/firewall/filter', array(
            array('.id' => '*2', 'name' => 'second', 'comment' => 'sync'),
            array('.id' => '*1', 'name' => 'first', 'comment' => 'sync')
        ));
        $router->failures['move'] = true;

        $this->assertFalse($sync->update($router));
        $this->assertContains('move failed /ip/firewall/filter *1', $sync->logs);
    }

    public function testSetSectionNeverAddsOrRemovesEntries()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('queues', '/queue/simple', 'set', 'name', array('comment' => 'sync'));
        $sync->config('queues', array('name' => 'existing', 'target' => '192.0.2.11/32'));
        $sync->config('queues', array('name' => 'missing', 'target' => '192.0.2.12/32'));
        $router = $this->routerWith('/queue/simple', array(
            array('.id' => '*1', 'name' => 'existing', 'target' => '192.0.2.10/32', 'comment' => 'sync'),
            array('.id' => '*old', 'name' => 'old', 'comment' => 'sync')
        ));

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            array(array('set', '/queue/simple', array('target' => '192.0.2.11/32', '.id' => '*1'))),
            $router->mutatingRecords()
        );
    }

    public function testValueSectionSetsOnlyDifferingFieldsAndHonorsLegacyEquivalence()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('identity', '/system/identity', 'value');
        $sync->config('identity', array('name' => 'router', 'disabled' => false, 'comment' => '', 'list' => 'a,b'));
        $router = $this->routerWith('/system/identity', array(
            'name' => 'router',
            'list' => 'a;b'
        ));

        $this->assertTrue($sync->update($router));
        $this->assertSame(
            array(array('set', '/system/identity', array('disabled' => false, 'comment' => ''))),
            $router->mutatingRecords()
        );
    }

    private function queueSynchronizer()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->section('queues', '/queue/simple', 'addset', 'name', array('comment' => 'sync'));
        $sync->pass('queues', array('comment' => 'sync'));

        return $sync;
    }

    private function routerWith($path, $records)
    {
        $router = new SynchronizingRouter();
        $router->responses[$path] = $records;

        return $router;
    }
}
