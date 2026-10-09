<?php

$vendor = getenv('ROUTEROS_TEST_VENDOR');
if ($vendor === false || $vendor === '') {
    $vendor = __DIR__ . '/../vendor';
}

require $vendor . '/autoload.php';

if (!class_exists('PHPUnit\\Framework\\TestCase') && class_exists('PHPUnit_Framework_TestCase')) {
    class_alias('PHPUnit_Framework_TestCase', 'PHPUnit\\Framework\\TestCase');
}
