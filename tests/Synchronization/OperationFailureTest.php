<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests\Synchronization;

use MitinSany\RouterOS\ConfigurationSynchronizer;
use MitinSany\RouterOS\Tests\Support\SynchronizingRouter;
use PHPUnit\Framework\TestCase;

class OperationFailureTest extends TestCase
{
    /** @dataProvider failureModes */
    public function testMutationFailuresRespectModeAndPreserveDiagnostics($operation, $tolerant)
    {
        $sync = new ConfigurationSynchronizer();
        $sync->continueOnError($tolerant);
        $type = $operation === 'value' ? 'value' : ($operation === 'move' ? 'addset_order' : 'addset');
        $sync->section('records', '/records', $type, 'name', ['comment' => 'sync']);
        $sync->pass('records', ['comment' => 'sync']);
        $router = new SynchronizingRouter();
        $router->errorString = 'operation denied';
        $router->responses['/records'] = [];
        $actualOperation = $operation === 'value' ? 'set' : $operation;
        if ($operation === 'set' || $operation === 'value') {
            $sync->config('records', ['name' => 'one', 'target' => 'new']);
            $router->responses['/records'] = [
                ['.id' => '*1', 'name' => 'one', 'comment' => 'sync', 'target' => 'old']
            ];
        } elseif ($operation === 'add') {
            $sync->config('records', ['name' => 'one']);
        } elseif ($operation === 'remove') {
            $router->responses['/records'] = [['.id' => '*1', 'name' => 'old', 'comment' => 'sync']];
        } else {
            $sync->config('records', ['name' => 'one']);
            $sync->config('records', ['name' => 'two']);
            $router->responses['/records'] = [
                ['.id' => '*2', 'name' => 'two', 'comment' => 'sync'],
                ['.id' => '*1', 'name' => 'one', 'comment' => 'sync']
            ];
        }
        $router->failures[$actualOperation] = true;
        $sync->section('after', '/system/identity', 'value');
        $sync->config('after', ['name' => 'unchanged']);
        $router->responses['/system/identity'] = [['name' => 'unchanged']];

        $this->assertSame($tolerant, $sync->update($router));
        $id = $operation === 'value' || $operation === 'add' ? '' : ' *1';
        $errors = [$actualOperation . ' failed /records' . $id . ': operation denied'];
        if ($operation === 'move' && $tolerant) {
            $errors[] = 'move failed /records *2: operation denied';
        }
        $this->assertSame($errors, $sync->errors);
        $this->assertSame($sync->errors, $sync->logs);
        $last = end($router->records);
        $this->assertSame($tolerant ? 'getAll' : $actualOperation, $last[0]);
        $this->assertSame($tolerant ? '/system/identity' : '/records', $last[1]);
    }

    public function failureModes()
    {
        $cases = [];
        foreach (['set', 'value', 'add', 'remove', 'move'] as $operation) {
            $cases[] = [$operation, false];
            $cases[] = [$operation, true];
        }

        return $cases;
    }

    public function testStandaloneErrorsContinueAndAreResetOnNextUpdate()
    {
        $sync = new ConfigurationSynchronizer();
        $sync->continueOnError();
        $sync->config('/first', ['name' => 'one']);
        $sync->config('/second', ['name' => 'two']);
        $router = new SynchronizingRouter();
        $router->failures['set'] = true;

        $this->assertTrue($sync->update($router));
        $this->assertSame(['set failed /first', 'set failed /second'], $sync->errors);
        unset($router->failures['set']);
        $this->assertTrue($sync->update($router));
        $this->assertSame([], $sync->errors);
        $this->assertSame(['set /first', 'set /second'], $sync->logs);
    }
}
