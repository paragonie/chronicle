<?php

if (!\is_readable(CHRONICLE_APP_ROOT . '/local/settings.json')) {
    echo 'Settings are not loaded.', PHP_EOL;
    exit(1);
}

$settings = \json_decode(
    (string) \file_get_contents(CHRONICLE_APP_ROOT . '/local/settings.json'),
    true
);
$db = \ParagonIE\EasyDB\Factory::create(
    $settings['database']['dsn'],
    $settings['database']['username'] ?? '',
    $settings['database']['password'] ?? '',
    $settings['database']['options'] ?? []
);

\ParagonIE\Chronicle\Chronicle::setDatabase($db);
return $db;
