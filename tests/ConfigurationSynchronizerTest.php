<?php

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
        $synchronizer->config('/queue/simple', ['name' => '{{router}}', 'comment' => 'managed']);
        $router = new RecordingRouter();

        $this->assertTrue($synchronizer->update($router));
        $this->assertSame(
            [['/queue/simple', ['name' => 'edge', 'comment' => 'managed']]],
            $router->sets
        );
    }

    public function testIgnoreSkipsMatchingConfiguration()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $synchronizer->config('/queue/simple', ['name' => 'skip']);
        $synchronizer->ignore('/queue/simple', ['name' => 'skip']);
        $router = new RecordingRouter();

        $this->assertTrue($synchronizer->update($router));
        $this->assertSame([], $router->sets);
    }

    public function testPassRulesExcludeConfigurationThatDoesNotMatch()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $synchronizer->config('/queue/simple', ['name' => 'edge', 'comment' => 'manual']);
        $synchronizer->pass('/queue/simple', ['comment' => 'managed']);
        $router = new RecordingRouter();

        $this->assertTrue($synchronizer->update($router));
        $this->assertSame([], $router->sets);
    }

    public function testParseFileLoadsIndependentConfigurationGrammar()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $router = new RecordingRouter();

        $this->assertTrue($synchronizer->parseFile(__DIR__ . '/fixtures/synchronizer.conf'));
        $this->assertTrue($synchronizer->update($router));
        $this->assertSame(
            [['/queue/simple', ['name' => 'edge', 'comment' => 'managed']]],
            $router->sets
        );
    }

    public function testUpdateSectionOnlyAppliesRegisteredSectionPath()
    {
        $synchronizer = new ConfigurationSynchronizer();
        $synchronizer->section('queues', '/queue/simple', 'set', 'name');
        $synchronizer->config('/queue/simple', ['name' => 'edge']);
        $synchronizer->config('/ip/address', ['address' => '192.0.2.1/24']);
        $router = new RecordingRouter();
        $router->responses['/queue/simple'] = [
            ['.id' => '*1', 'name' => 'edge', 'comment' => 'old']
        ];

        $this->assertTrue($synchronizer->updateSection($router, 'queues'));
        $this->assertSame([], $router->sets);
    }
}
