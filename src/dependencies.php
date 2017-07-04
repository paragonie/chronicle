<?php
use Slim\Container;
// DIC configuration

if (!isset($app)) {
    throw new Error('Variable $app is not defined');
}
if (!($app instanceof \Slim\App)) {
    throw new Error('Variable $app is not an App');
}

/** @var Container $container */
$container = $app->getContainer();

// view renderer
$container['renderer'] = function (Container $c): \Slim\Views\PhpRenderer {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function (Container $c): \Monolog\Logger {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// easydb
$container['database'] = function (Container $c): \ParagonIE\EasyDB\EasyDB {
    return \ParagonIE\Chronicle\Chronicle::getDatabase();
};
