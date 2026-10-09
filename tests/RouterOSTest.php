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
            array(
                array('!re', '=name=ether1', '=.id=*1'),
                array('!done')
            )
        );
        $router = new TestRouterOS($transport);

        $this->assertSame(
            array(array('name' => 'ether1', '.id' => '*1')),
            $router->getAll('/interface', array('.id', 'name'), array('disabled' => 'no'))
        );
        $this->assertSame(
            array(array('/interface/print', '=.proplist=.id,name', '=disabled=no')),
            $transport->sentences
        );
    }

    public function testSetReturnsFalseAfterTrap()
    {
        $transport = new ScriptedTransport(array(array('!trap', '=message=not allowed')));
        $router = new TestRouterOS($transport);

        $this->assertFalse($router->set('/queue/simple', array('.id' => '*1', 'comment' => 'managed')));
        $this->assertSame(
            array(array('/queue/simple/set', '=.id=*1', '=comment=managed')),
            $transport->sentences
        );
    }

    public function testReadOnlyPreventsMutatingCommand()
    {
        $transport = new ScriptedTransport(array());
        $router = new TestRouterOS($transport);
        $router->readOnly = true;

        $this->assertFalse($router->remove('/ip/address', '*1'));
        $this->assertSame(array(), $transport->sentences);
    }

    public function testListenRoutesTaggedResponsesAndCanStopDispatch()
    {
        $transport = new ScriptedTransport(
            array(
                array('!re', '=.tag=callback-1', '=name=ether1'),
                array('!done', '=.tag=callback-1')
            )
        );
        $router = new TestRouterOS($transport);
        $events = array();
        $tag = $router->listen('/interface', false, function ($connection, $success, $reply) use (&$events) {
            $events[] = array($connection, $success, $reply);
        });
        $continue = false;

        $this->assertSame('callback-1', $tag);
        $this->assertFalse($router->dispatch($continue));
        $this->assertCount(1, $events);
        $this->assertTrue($events[0][1]);
        $this->assertSame(array('name' => 'ether1'), $events[0][2]);
        $this->assertSame(
            array(array('/interface/listen', '=.tag=callback-1')),
            $transport->sentences
        );
    }
}
