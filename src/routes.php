<?php
use ParagonIE\Chronicle\Handlers\{
    Lookup,
    Publish,
    Register,
    Revoke
};
use ParagonIE\Chronicle\Middleware\{
    CheckAdminSignature,
    CheckClientSignature
};
use Psr\Http\Message\{
    ResponseInterface
};

// Routes

if (!isset($app)) {
    throw new Error('Variable $app is not defined');
}
if (!($app instanceof \Slim\App)) {
    throw new Error('Variable $app is not an App');
}
if (!isset($container)) {
    throw new Error('Variable $container is not defined');
}
if (!($container instanceof \Slim\Container)) {
    throw new Error('Variable $app is not a Container');
}

$app->group('/chronicle', function () {

    // Admin only:
    $this->group('', function () {
        $this->post('/register', Register::class);
        $this->post('/revoke', Revoke::class);
    })->add(CheckAdminSignature::class);

    // Clients:
    $this->post('/publish', Publish::class)
        ->add(CheckClientSignature::class);

    // Public:
    $this->get('/lasthash', 'lookup.lasthash');
    $this->get('/lookup/[{hash}]', 'lookup.hash');
    $this->get('/since/[{hash}]', 'lookup.since');
    $this->get('/export', 'lookup.export');
});

$app->get('/', function ($request, $response, $args): ResponseInterface {
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$container[CheckAdminSignature::class] = function ($c) {
    return new CheckAdminSignature();
};
$container[Register::class] = function ($c) {
    return new Register();
};
$container[Revoke::class] = function ($c) {
    return new Revoke();
};
$container[Publish::class] = function ($c) {
    return new Publish();
};
$container['lookup.lasthash'] = function ($c) {
    return new Lookup('lasthash');
};
$container['lookup.hash'] = function ($c) {
    return new Lookup('hash');
};
$container['lookup.since'] = function ($c) {
    return new Lookup('since');
};
$container['lookup.export'] = function ($c) {
    return new Lookup('export');
};
