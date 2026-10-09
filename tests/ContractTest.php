<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests;

use PHPUnit\Framework\TestCase;

class ContractTest extends TestCase
{
    public function testPublicClassesCanBeAutoloaded()
    {
        $this->assertClassContract(
            'MitinSany\\RouterOS\\Api',
            [
                'debug', 'errorNo', 'errorString', 'attempts', 'connected', 'delay',
                'port', 'timeout', 'sock'
            ],
            [
                'connect', 'debug', 'write', 'encodeLength', 'read', 'parseResponse',
                'disconnect', 'parseResponseForSmarty', 'changeArrayKeyName', 'command'
            ]
        );

        $this->assertClassContract(
            'MitinSany\\RouterOS\\RouterOS',
            ['sock', 'hostname', 'readOnly'],
            [
                'setTimeout', 'getAll', 'set', 'reboot', 'fetchUrl', 'cancel', 'move',
                'add', 'remove', 'unsetValue', 'scan', 'frequencyMonitor', 'listen',
                'bandwidthTest', 'dispatch'
            ]
        );

        $this->assertClassContract(
            'MitinSany\\RouterOS\\ConfigurationSynchronizer',
            ['vars', 'logs', 'showIgnored', 'currentContext'],
            [
                '__get', '__set', '__isset', '__unset', 'variable',
                'configureWithArguments', 'configureReversedWithArguments',
                'ignoreWithArguments', 'passWithArguments', 'parseFile', 'replace',
                'error', 'define', 'config', 'cmd', 'ignore', 'pass', 'section', 'call',
                'update', 'updateSection'
            ]
        );
    }

    private function assertClassContract($className, array $properties, array $methods)
    {
        $this->assertTrue(class_exists($className));

        $reflection = new \ReflectionClass($className);
        foreach ($properties as $property) {
            $this->assertTrue($reflection->hasProperty($property));
            $this->assertTrue($reflection->getProperty($property)->isPublic());
        }

        foreach ($methods as $method) {
            $this->assertTrue($reflection->hasMethod($method));
            $this->assertTrue($reflection->getMethod($method)->isPublic());
        }
    }
}
