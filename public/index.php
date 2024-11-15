<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'home.phtml');
})->setName('home');

$app->get('/urls', function ($request, $response) {
    return $this->get('renderer')->render($response, 'sites.phtml');
})->setName('urls');

$app->post('/urls', function ($request, $response) {
    return $this->get('renderer')->render($response, 'sites.phtml');
})->setName('urls');

$app->get('/urls/{id}', function ($request, $response, array $args) {
    return $this->get('renderer')->render($response, 'show.phtml');
});

$app->run();