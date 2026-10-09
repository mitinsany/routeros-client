<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests;

use MitinSany\RouterOS\Tests\Support\ScriptedTransport;
use MitinSany\RouterOS\Tests\Support\TestRouterOS;
use PHPUnit\Framework\TestCase;

class RouterOSTest extends TestCase
{
    public function testGetAllBuildsPrintCommandAndReturnsRecords()
    {
        $transport = new ScriptedTransport(
            [
                ['!re', '=name=ether1', '=.id=*1'],
                ['!done']
            ]
        );
        $router = new TestRouterOS($transport);

        $this->assertSame(
            [['name' => 'ether1', '.id' => '*1']],
            $router->getAll('/interface', ['.id', 'name'], ['disabled' => 'no'])
        );
        $this->assertSame(
            [['/interface/print', '=.proplist=.id,name', '=disabled=no']],
            $transport->sentences
        );
    }

    public function testSetReturnsFalseAfterTrap()
    {
        $transport = new ScriptedTransport([['!trap', '=message=not allowed']]);
        $router = new TestRouterOS($transport);

        $this->assertFalse($router->set('/queue/simple', ['.id' => '*1', 'comment' => 'managed']));
        $this->assertSame(
            [['/queue/simple/set', '=.id=*1', '=comment=managed']],
            $transport->sentences
        );
    }

    public function testReadOnlyPreventsMutatingCommand()
    {
        $transport = new ScriptedTransport([]);
        $router = new TestRouterOS($transport);
        $router->readOnly = true;

        $this->assertFalse($router->remove('/ip/address', '*1'));
        $this->assertSame([], $transport->sentences);
    }

    public function testListenRoutesTaggedResponsesAndCanStopDispatch()
    {
        $transport = new ScriptedTransport(
            [
                ['!re', '=.tag=callback-1', '=name=ether1'],
                ['!done', '=.tag=callback-1']
            ]
        );
        $router = new TestRouterOS($transport);
        $events = [];
        $tag = $router->listen('/interface', false, function ($connection, $success, $reply) use (&$events) {
            $events[] = [$connection, $success, $reply];
        });
        $continue = false;

        $this->assertSame('callback-1', $tag);
        $this->assertFalse($router->dispatch($continue));
        $this->assertCount(1, $events);
        $this->assertTrue($events[0][1]);
        $this->assertSame(['name' => 'ether1'], $events[0][2]);
        $this->assertSame(
            [['/interface/listen', '=.tag=callback-1']],
            $transport->sentences
        );
    }
}
