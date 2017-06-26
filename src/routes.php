<?php
use ParagonIE\Chronicle\Handlers\{
    Lookup,
    Publish
};
use ParagonIE\Chronicle\Middleware\CheckClientSignature;
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
    $app->post('/publish', new Publish())
        ->add(new CheckClientSignature());
    $app->get('/lasthash', new Lookup('lasthash'));
    $app->get('/lookup/[{hash}]', new Lookup('hash'));
    $app->get('/since/[{hash}]', new Lookup('hash'));
    $app->get('/export', new Lookup('export'));

});

$app->get('/', function ($request, $response, $args): ResponseInterface {
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});
