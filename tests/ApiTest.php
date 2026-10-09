<?php

namespace MitinSany\RouterOS\Tests;

use MitinSany\RouterOS\Api;
use MitinSany\RouterOS\Tests\Support\ScriptedTransport;
use MitinSany\RouterOS\Tests\Support\TestApi;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    public function testConnectSendsPlainTextLoginAndAcceptsDone()
    {
        $transport = new ScriptedTransport(array(array('!done')));
        $api = new TestApi($transport);

        $this->assertTrue($api->connect('router.example', 'admin', 'secret'));
        $this->assertTrue($api->connected);
        $this->assertSame(3, $transport->timeout);
        $this->assertSame(
            array(array('/login', '=name=admin', '=password=secret')),
            $transport->sentences
        );
    }

    public function testConnectReturnsFalseForTrap()
    {
        $transport = new ScriptedTransport(array(array('!trap', '=message=denied'), array('!done')));
        $api = new TestApi($transport);

        $this->assertFalse($api->connect('router.example', 'admin', 'wrong'));
        $this->assertFalse($api->connected);
        $this->assertSame('denied', $api->errorString);
    }

    public function testCommandWritesAttributesAndParsesRecords()
    {
        $transport = new ScriptedTransport(
            array(
                array('!re', '=name=ether1', '=comment=a=b'),
                array('!done')
            )
        );
        $api = new TestApi($transport);

        $this->assertSame(
            array(array('name' => 'ether1', 'comment' => 'a=b')),
            $api->command('/interface/print', array('detail' => 'yes'))
        );
        $this->assertSame(
            array(array('/interface/print', '=detail=yes')),
            $transport->sentences
        );
    }

    public function testCommandReturnsFalseAfterTrap()
    {
        $transport = new ScriptedTransport(array(array('!trap', '=message=not allowed'), array('!done')));
        $api = new TestApi($transport);

        $this->assertFalse($api->command('/system/reboot'));
        $this->assertSame('not allowed', $api->errorString);
    }

    public function testCommandDrainsDoneAfterTrapBeforeNextRequest()
    {
        $transport = new ScriptedTransport(
            array(
                array('!trap', '=message=not allowed'),
                array('!done'),
                array('!re', '=name=ether1'),
                array('!done')
            )
        );
        $api = new TestApi($transport);

        $this->assertFalse($api->command('/system/reboot'));
        $this->assertSame(
            array(array('name' => 'ether1')),
            $api->command('/interface/print')
        );
    }

    public function testDisconnectClosesTransport()
    {
        $transport = new ScriptedTransport(array(array('!done')));
        $api = new TestApi($transport);
        $api->connect('router.example', 'admin', 'secret');

        $api->disconnect();

        $this->assertFalse($api->connected);
        $this->assertTrue($transport->closed);
    }
}
