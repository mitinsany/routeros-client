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
        $transport = new ScriptedTransport([['!done']]);
        $api = new TestApi($transport);

        $this->assertTrue($api->connect('router.example', 'admin', 'secret'));
        $this->assertTrue($api->connected);
        $this->assertSame(3, $transport->timeout);
        $this->assertSame(
            [['/login', '=name=admin', '=password=secret']],
            $transport->sentences
        );
    }

    public function testConnectReturnsFalseForTrap()
    {
        $transport = new ScriptedTransport([['!trap', '=message=denied'], ['!done']]);
        $api = new TestApi($transport);

        $this->assertFalse($api->connect('router.example', 'admin', 'wrong'));
        $this->assertFalse($api->connected);
        $this->assertSame('denied', $api->errorString);
    }

    public function testCommandWritesAttributesAndParsesRecords()
    {
        $transport = new ScriptedTransport(
            [
                ['!re', '=name=ether1', '=comment=a=b'],
                ['!done']
            ]
        );
        $api = new TestApi($transport);

        $this->assertSame(
            [['name' => 'ether1', 'comment' => 'a=b']],
            $api->command('/interface/print', ['detail' => 'yes'])
        );
        $this->assertSame(
            [['/interface/print', '=detail=yes']],
            $transport->sentences
        );
    }

    public function testCommandReturnsFalseAfterTrap()
    {
        $transport = new ScriptedTransport([['!trap', '=message=not allowed'], ['!done']]);
        $api = new TestApi($transport);

        $this->assertFalse($api->command('/system/reboot'));
        $this->assertSame('not allowed', $api->errorString);
    }

    public function testCommandDrainsDoneAfterTrapBeforeNextRequest()
    {
        $transport = new ScriptedTransport(
            [
                ['!trap', '=message=not allowed'],
                ['!done'],
                ['!re', '=name=ether1'],
                ['!done']
            ]
        );
        $api = new TestApi($transport);

        $this->assertFalse($api->command('/system/reboot'));
        $this->assertSame(
            [['name' => 'ether1']],
            $api->command('/interface/print')
        );
    }

    public function testDisconnectClosesTransport()
    {
        $transport = new ScriptedTransport([['!done']]);
        $api = new TestApi($transport);
        $api->connect('router.example', 'admin', 'secret');

        $api->disconnect();

        $this->assertFalse($api->connected);
        $this->assertTrue($transport->closed);
    }
}
