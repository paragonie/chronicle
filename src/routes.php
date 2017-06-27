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

$app->group('/chronicle', function () use ($app) {
    $app->post('/publish', new Register($app))
        ->add(new CheckAdminSignature());
    $app->post('/publish', new Revoke($app))
        ->add(new CheckAdminSignature());
    $app->post('/publish', new Publish($app))
        ->add(new CheckClientSignature());
    $app->get('/lasthash', new Lookup($app, 'lasthash'));
    $app->get('/lookup/[{hash}]', new Lookup($app, 'hash'));
    $app->get('/since/[{hash}]', new Lookup($app, 'since'));
    $app->get('/export', new Lookup($app, 'export'));

});

$app->get('/', function ($request, $response, $args): ResponseInterface {
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});
