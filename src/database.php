<?php
declare(strict_types=1);

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\Exception\InstanceNotFoundException;

if (!\is_readable(CHRONICLE_APP_ROOT . '/local/settings.json')) {
    echo 'Settings are not loaded.', PHP_EOL;
    exit(1);
}

/** @var array<string, array<string, string>> $settings */
$settings = \json_decode(
    (string) \file_get_contents(CHRONICLE_APP_ROOT . '/local/settings.json'),
    true
);
/** @var \ParagonIE\EasyDB\EasyDB $db */
$db = \ParagonIE\EasyDB\Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? '',
    $settings['database']['password'] ?? '',
    (array) ($settings['database']['options'] ?? [])
);

if (!empty($_GET['instance'])) {
    try {
        if (\is_string($_GET['instance'])) {
            /** @var string $instance */
            $instance = $_GET['instance'];
            if (\array_key_exists($instance, $settings['instances'])) {
                Chronicle::setTablePrefix($settings['instances'][$instance]);
            }
        }
    } catch (InstanceNotFoundException $ex) {
        echo $ex->getMessage(), PHP_EOL;
        exit(1);
    }
}

Chronicle::setDatabase($db);
return $db;
