<?php
declare(strict_types=1);

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

\ParagonIE\Chronicle\Chronicle::setDatabase($db);
return $db;
