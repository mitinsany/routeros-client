<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests;

use MitinSany\RouterOS\ConfigurationSynchronizer;
use MitinSany\RouterOS\Tests\Support\RecordingRouter;
use PHPUnit\Framework\TestCase;

class ConfigurationSynchronizerTest extends TestCase
{
    public function testVariablesAreAvailableThroughMagicPropertiesAndReplacement()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $synchronizer->router = 'edge';

        $this->assertSame('edge', $synchronizer->variable('router'));
        $this->assertSame('queue-edge', $synchronizer->replace('queue-{{router}}'));
        $this->assertTrue(isset($synchronizer->router));
    }

    public function testUpdateAppliesConfigurationAfterVariableReplacement()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $synchronizer->define('router', 'edge');
        $synchronizer->config('/queue/simple', array('name' => '{{router}}', 'comment' => 'managed'));
        $router = new RecordingRouter();

        $this->assertTrue($synchronizer->update($router));
        $this->assertSame(
            array(array('/queue/simple', array('name' => 'edge', 'comment' => 'managed'))),
            $router->sets
        );
    }

    public function testIgnoreSkipsMatchingConfiguration()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $synchronizer->config('/queue/simple', array('name' => 'skip'));
        $synchronizer->ignore('/queue/simple', array('name' => 'skip'));
        $router = new RecordingRouter();

        $this->assertTrue($synchronizer->update($router));
        $this->assertSame(array(), $router->sets);
    }

    public function testPassRulesExcludeConfigurationThatDoesNotMatch()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $synchronizer->config('/queue/simple', array('name' => 'edge', 'comment' => 'manual'));
        $synchronizer->pass('/queue/simple', array('comment' => 'managed'));
        $router = new RecordingRouter();

        $this->assertTrue($synchronizer->update($router));
        $this->assertSame(array(), $router->sets);
    }

    public function testParseFileLoadsIndependentConfigurationGrammar()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $router = new RecordingRouter();

        $this->assertTrue($synchronizer->parseFile(__DIR__ . '/fixtures/synchronizer.conf'));
        $this->assertTrue($synchronizer->update($router));
        $this->assertSame(
            array(array('/queue/simple', array('name' => 'edge', 'comment' => 'managed'))),
            $router->sets
        );
    }

    public function testUpdateSectionOnlyAppliesRegisteredSectionPath()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $synchronizer->section('queues', '/queue/simple', 'set', 'name');
        $synchronizer->config('/queue/simple', array('name' => 'edge'));
        $synchronizer->config('/ip/address', array('address' => '192.0.2.1/24'));
        $router = new RecordingRouter();
        $router->responses['/queue/simple'] = array(
            array('.id' => '*1', 'name' => 'edge', 'comment' => 'old')
        );

        $this->assertTrue($synchronizer->updateSection($router, 'queues'));
        $this->assertSame(array(), $router->sets);
    }
}
