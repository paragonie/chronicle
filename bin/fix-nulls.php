<?php
declare(strict_types=1);

use ParagonIE\EasyDB\{
    EasyDB,
    Factory
};

$root = \dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once $root . '/cli-autoload.php';

if (!\is_readable($root . '/local/settings.json')) {
    echo 'Settings are not loaded.', PHP_EOL;
    exit(1);
}

/** @var array<string, string> $settings */
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

if ($db->getDriver() === 'sqlite') {
    $db->run("UPDATE chronicle_clients SET isAdmin = 0 WHERE isAdmin IS NULL");
} else {
    $db->run("UPDATE chronicle_clients SET isAdmin = FALSE WHERE isAdmin IS NULL");
}
