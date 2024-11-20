<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;
use Carbon\Carbon;

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

$connect = Connection::get()->connect();

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'home.phtml');
})->setName('home');

$app->get('/urls', function ($request, $response) use ($connect) {
    $sqlGetAll = 'SELECT * FROM urls ORDER BY created_at DESC';
    $getAll = $connect->prepare($sqlGetAll);
    $getAll->execute();
    $allUrls = $getAll->fetchAll(\PDO::FETCH_ASSOC);

    $params = [
        'urls' => $allUrls
    ];

    return $this->get('renderer')->render($response, 'sites.phtml', $params);
})->setName('urls');

$app->post('/urls', function ($request, $response) use ($router, $connect) {
    $dataRequest = $request->getParsedBodyParam('url');

    $errors = new Valitron\Validator($dataRequest);
    $errors->rule('required', 'name');
    if(!$errors->validate()) {
        $message = 'URL не должен быть пустым';
        $params = ['errors' => $errors, 'message' => $message];
        return $this->get('renderer')->render($response->withStatus(422), 'home.phtml', $params);
    }

    $errors->rule('lengthMax', 'name', 255);
    if(!$errors->validate()) {
        $message = 'URL не должен превышать 255 символов';
        $params = ['errors' => $errors, 'message' => $message];
        return $this->get('renderer')->render($response->withStatus(422), 'home.phtml', $params);
    }

    $errors->rule('url', 'name');
    if(!$errors->validate()) {
        $message = 'Некорректный URL';
        $params = ['errors' => $errors, 'message' => $message];
        return $this->get('renderer')->render($response->withStatus(422), 'home.phtml', $params);
    }

    $urlData = parse_url(mb_strtolower($dataRequest['name']));
    $urlName = "{$urlData['scheme']}://{$urlData['host']}";
    $now = Carbon::now();

    $sqlAddUrl = 'INSERT INTO urls (name, created_at) VALUES (:name, :created_at)';
    $addUrl = $connect->prepare($sqlAddUrl);
    $addUrl->bindValue(':name', $urlName);
    $addUrl->bindValue(':created_at', $now);
    $addUrl->execute();

    $sqlGetId = 'SELECT id FROM urls WHERE name = :name';
    $getId = $connect->prepare($sqlGetId);
    $getId->bindValue(':name', $urlName);
    $getId->execute();
    $id = $getId->fetchColumn();
    $url = $router->urlFor('check', ['id' => $id]);

    return $response->withRedirect($url);
});

$app->get('/urls/{id}', function ($request, $response, array $args) use ($connect) {
    $id = $args['id'];
    $sqlGetLine = 'SELECT * FROM urls WHERE id = :id';
    $getLine = $connect->prepare($sqlGetLine);
    $getLine->bindValue(':id', $id);
    $getLine->execute();
    $urlData = $getLine->fetch(\PDO::FETCH_ASSOC);
    
    $params = [
        'url' => $urlData['name'],
        'id' => $urlData['id'],
        'now' => $urlData['created_at']
    ];

    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('check');




$app->post('/urls/{id}/check', function ($request, $response, array $args) {
    return $this->get('renderer')->render($response, 'show.phtml');
});

$app->run();