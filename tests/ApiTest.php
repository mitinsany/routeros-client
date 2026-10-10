<?php

namespace MitinSany\RouterOS\Tests;

use MitinSany\RouterOS\Api;
use MitinSany\RouterOS\Enum\LoginMode;
use MitinSany\RouterOS\Tests\Support\ScriptedTransport;
use MitinSany\RouterOS\Tests\Support\TestApi;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    public function testLoginModeClassExists()
    {
        $this->assertTrue(class_exists('MitinSany\\RouterOS\\Enum\\LoginMode'));
    }

    public function testDefaultConnectUsesAutoModeAndSendsOneModernRequest()
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

    public function testPlainLoginSendsOneRequestWithoutFallback()
    {
        $transport = new ScriptedTransport([['!done']]);
        $api = new TestApi($transport);

        $this->assertTrue($api->connect('router.example', 'admin', 'secret', LoginMode::PLAIN));
        $this->assertSame(
            [['/login', '=name=admin', '=password=secret']],
            $transport->sentences
        );
    }

    public function testPlainLoginRejectsLegacyChallengeWithoutFallback()
    {
        $transport = new ScriptedTransport([['!done', '=ret=0123456789abcdef0123456789abcdef']]);
        $api = new TestApi($transport);

        $this->assertFalse($api->connect('router.example', 'admin', 'secret', LoginMode::PLAIN));
        $this->assertCount(1, $transport->sentences);
    }

    public function testLegacyLoginSendsChallengeAndResponseRequests()
    {
        $challenge = '0123456789abcdef0123456789abcdef';
        $transport = new ScriptedTransport(
            [
                ['!done', '=ret=' . $challenge],
                ['!done']
            ]
        );
        $api = new TestApi($transport);

        $this->assertTrue($api->connect('router.example', 'admin', 'secret', LoginMode::LEGACY));
        $this->assertSame(
            [
                ['/login'],
                ['/login', '=name=admin', '=response=00ebbe7c3df6b3d902bfd1f355c6e63289']
            ],
            $transport->sentences
        );
    }

    public function testAutoLoginFallsBackToLegacyChallengeOnSameTransport()
    {
        $challenge = '0123456789abcdef0123456789abcdef';
        $transport = new ScriptedTransport(
            [
                ['!done', '=ret=' . $challenge],
                ['!done']
            ]
        );
        $api = new TestApi($transport);

        $this->assertTrue($api->connect('router.example', 'admin', 'secret'));
        $this->assertSame(
            [
                ['/login', '=name=admin', '=password=secret'],
                ['/login', '=name=admin', '=response=00ebbe7c3df6b3d902bfd1f355c6e63289']
            ],
            $transport->sentences
        );
    }

    public function testAutoLoginDoesNotFallbackAfterTrap()
    {
        $transport = new ScriptedTransport([['!trap', '=message=denied'], ['!done']]);
        $api = new TestApi($transport);

        $this->assertFalse($api->connect('router.example', 'admin', 'wrong'));
        $this->assertSame('denied', $api->errorString);
        $this->assertCount(1, $transport->sentences);
    }

    public function testAutoLoginDoesNotFallbackAfterFatalReply()
    {
        $transport = new ScriptedTransport([['!fatal', '=message=closed'], ['!done']]);
        $api = new TestApi($transport);

        $this->assertFalse($api->connect('router.example', 'admin', 'secret'));
        $this->assertSame('closed', $api->errorString);
        $this->assertCount(1, $transport->sentences);
    }

    public function testAutoLoginDoesNotFallbackAfterTransportFailure()
    {
        $transport = new ScriptedTransport([]);
        $api = new TestApi($transport);

        $this->assertFalse($api->connect('router.example', 'admin', 'secret'));
        $this->assertSame('No scripted RouterOS response remains.', $api->errorString);
        $this->assertCount(1, $transport->sentences);
    }

    public function testAutoLoginRejectsEmptyAndInvalidLegacyChallenges()
    {
        foreach (['', 'not-a-hex-challenge'] as $challenge) {
            $transport = new ScriptedTransport([['!done', '=ret=' . $challenge]]);
            $api = new TestApi($transport);

            $this->assertFalse($api->connect('router.example', 'admin', 'secret'));
            $this->assertNotEmpty($api->errorString);
            $this->assertCount(1, $transport->sentences);
        }
    }

    public function testLegacyLoginRejectsModernDoneWithoutChallenge()
    {
        $transport = new ScriptedTransport([['!done']]);
        $api = new TestApi($transport);

        $this->assertFalse($api->connect('router.example', 'admin', 'secret', LoginMode::LEGACY));
        $this->assertNotEmpty($api->errorString);
        $this->assertSame([['/login']], $transport->sentences);
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
