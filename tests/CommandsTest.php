<?php

namespace MitinSany\RouterOS\Tests;

use MitinSany\RouterOS\Tests\Support\ScriptedTransport;
use MitinSany\RouterOS\Tests\Support\TestRouterOS;
use PHPUnit\Framework\TestCase;

class CommandsTest extends TestCase
{
    /**
     * @dataProvider helperCommandProvider
     */
    public function testHelpersUseExpectedRouterOsCommand($method, $arguments, $expected)
    {
        $transport = new ScriptedTransport([['!done']]);
        $router = new TestRouterOS($transport);

        $this->assertTrue(call_user_func_array([$router, $method], $arguments));
        $this->assertSame([$expected], $transport->sentences);
    }

    public function helperCommandProvider()
    {
        return [
            ['reboot', [], ['/system/reboot']],
            [
                'fetchUrl',
                ['https://example.invalid/file'],
                ['/tool/fetch', '=url=https://example.invalid/file']
            ],
            ['scan', ['wlan1'], ['/interface/wireless/scan', '=interface=wlan1', '=duration=00:10:00']],
            [
                'frequencyMonitor',
                ['wlan1'],
                ['/interface/wireless/frequency-monitor', '=interface=wlan1', '=duration=00:02:00']
            ],
            [
                'bandwidthTest',
                ['198.51.100.1'],
                ['/tool/bandwidth-test', '=address=198.51.100.1', '=speed=1M', '=protocol=tcp']
            ]
        ];
    }
}
