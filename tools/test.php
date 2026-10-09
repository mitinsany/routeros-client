<?php

$root = dirname(__DIR__);
$profile = PHP_VERSION_ID < 70000 ? 'php54' : (PHP_VERSION_ID < 70200 ? 'php70'
    : (PHP_VERSION_ID < 80000 ? 'php72' : 'php80'));
$profileRoot = __DIR__ . '/phpunit/' . $profile;
$binary = $profileRoot . '/vendor/bin/phpunit';

if (!is_file($binary)) {
    fwrite(STDERR, 'Install tests: composer --working-dir=' . $profileRoot . " install\n");
    exit(2);
}

putenv('ROUTEROS_TEST_PROFILE=' . $profile);
putenv('ROUTEROS_TEST_VENDOR=' . $profileRoot . '/vendor');
chdir($root);

$arguments = array_merge(array(PHP_BINARY, $binary, '-c', 'phpunit.xml.dist'), array_slice($argv, 1));
$command = implode(' ', array_map('escapeshellarg', $arguments));
passthru($command, $status);
exit($status);
