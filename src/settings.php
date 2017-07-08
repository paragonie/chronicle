<?php
declare(strict_types=1);

/* Local settings; not checked into git. */
$localSettings = [];
if (\is_readable(CHRONICLE_APP_ROOT . '/local/settings.json')) {
    $settingsFile = \file_get_contents(CHRONICLE_APP_ROOT . '/local/settings.json');
    if (\is_string($settingsFile)) {
        $localSettings = \json_decode($settingsFile, true);
    }
}

/* These are the defaults. You can override them locally by updating ../local/settings.json: */
$settings = [
    'displayErrorDetails' => false, // set to false in production
    'addContentLengthHeader' => false, // Allow the web server to send the content-length header

    // Renderer settings
    'renderer' => [
        'template_path' => CHRONICLE_APP_ROOT . '/templates/',
    ],

    // Monolog settings
    'logger' => [
        'name' => 'paragonie-chronicle',
        'path' => CHRONICLE_APP_ROOT . '/logs/app.log',
        'level' => \Monolog\Logger::DEBUG,
    ],
];

return [
    'settings' => $localSettings + $settings
];
