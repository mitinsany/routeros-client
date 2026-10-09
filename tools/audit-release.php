<?php

$root = dirname(__DIR__);
$required = array('LICENSE', 'README.md', 'composer.json', 'docs/provenance.md');
$forbidden = array('NodenySync', 'app/Mikrotik', 'docs/superpowers');
$failures = array();

foreach ($required as $path) {
    if (!is_file($root . '/' . $path)) {
        $failures[] = 'Missing required release file: ' . $path;
    }
}

foreach ($forbidden as $path) {
    if (file_exists($root . '/' . $path)) {
        $failures[] = 'Forbidden release path: ' . $path;
    }
}

$composer = json_decode(file_get_contents($root . '/composer.json'), true);
if (!is_array($composer) || !isset($composer['license']) || $composer['license'] !== 'MIT') {
    $failures[] = 'composer.json must declare the MIT license.';
}

if (count($failures) > 0) {
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . PHP_EOL);
    }

    exit(1);
}

echo "Release audit passed." . PHP_EOL;
