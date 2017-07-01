<?php
use ParagonIE\EasyDB\{
    EasyDB,
    Factory
};

$root = \dirname(__DIR__);
require_once $root . '/cli-autoload.php';

if (!\is_readable($root . '/local/settings.json')) {
    echo 'Settings are not loaded.', PHP_EOL;
    exit(1);
}

$settings = \json_decode(
    (string) \file_get_contents($root . '/local/settings.json'),
    true
);
/** @var EasyDB $db */
$db = Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? '',
    $settings['database']['password'] ?? '',
    $settings['database']['options'] ?? []
);

// Run the scheduler:
(new ParagonIE\Chronicle\Scheduled($db))
    ->run();
