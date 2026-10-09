<?php

declare(strict_types=1);

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
        $transport = new ScriptedTransport(array(array('!done')));
        $router = new TestRouterOS($transport);

        $this->assertTrue(call_user_func_array(array($router, $method), $arguments));
        $this->assertSame(array($expected), $transport->sentences);
    }

    public function helperCommandProvider()
    {
        return array(
            array('reboot', array(), array('/system/reboot')),
            array(
                'fetchUrl',
                array('https://example.invalid/file'),
                array('/tool/fetch', '=url=https://example.invalid/file')
            ),
            array('scan', array('wlan1'), array('/interface/wireless/scan', '=interface=wlan1', '=duration=00:10:00')),
            array(
                'frequencyMonitor',
                array('wlan1'),
                array('/interface/wireless/frequency-monitor', '=interface=wlan1', '=duration=00:02:00')
            ),
            array(
                'bandwidthTest',
                array('198.51.100.1'),
                array('/tool/bandwidth-test', '=address=198.51.100.1', '=speed=1M', '=protocol=tcp')
            )
        );
    }
}
