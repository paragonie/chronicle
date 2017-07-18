<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle\CliTests;

use ParagonIE\Chronicle\Chronicle;

if (file_exists(__DIR__ . '/client.json')) {
    exit(0);
}
if (file_exists(__DIR__ . '/client-admin.json')) {
    exit(0);
}

require_once __DIR__ . '/cli-include.php';

Chronicle::getDatabase()->beginTransaction();
Chronicle::getDatabase()->delete(
    'chronicle_clients',
    [
        'isAdmin' => false,
        'publicid' => 'CLI-testing-user'
    ]
);
Chronicle::getDatabase()->delete(
    'chronicle_clients',
    [
        'isAdmin' => true,
        'publicid' => 'CLI-admin-user'
    ]
);
Chronicle::getDatabase()->commit();

unlink(__DIR__.'/client.json');
unlink(__DIR__.'/client-admin.json');
