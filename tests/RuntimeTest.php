<?php

namespace MitinSany\RouterOS\Tests;

use PHPUnit\Framework\TestCase;

class RuntimeTest extends TestCase
{
    public function testRunnerDeclaresItsPhpProfile()
    {
        if (PHP_VERSION_ID < 70000) {
            $expected = 'php54';
        } elseif (PHP_VERSION_ID < 70200) {
            $expected = 'php70';
        } elseif (PHP_VERSION_ID < 80000) {
            $expected = 'php72';
        } else {
            $expected = 'php80';
        }

        $this->assertSame($expected, getenv('ROUTEROS_TEST_PROFILE'));
    }
}
