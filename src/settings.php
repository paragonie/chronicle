<?php
define('CHRONICLE_APP_ROOT', \dirname(__DIR__));

/* Local settings; not checked into git. */
$localSettings = [];
if (\is_readable(\dirname(__DIR__) . '/local/settings.json')) {
    $settingsFile = \file_get_contents(\dirname(__DIR__) . '/local/settings.json');
    if (\is_string($settingsFile)) {
        $localSettings = \json_decode($settingsFile, true);
    }
}

$settings = [
    'displayErrorDetails' => false, // set to false in production
    'addContentLengthHeader' => false, // Allow the web server to send the content-length header

    // Renderer settings
    'renderer' => [
        'template_path' => __DIR__ . '/../templates/',
    ],

    // Monolog settings
    'logger' => [
        'name' => 'paragonie-chronicle',
        'path' => __DIR__ . '/../logs/app.log',
        'level' => \Monolog\Logger::DEBUG,
    ],
];

return [
    'settings' => $localSettings + $settings
];
