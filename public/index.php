<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Connection;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use DiDom\Document;
use Illuminate\Support\Collection;

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
    $sqlGetUrls =   'SELECT
                        urls.id,
                        url_checks.url_id,
                        urls.name,
                        MAX(url_checks.created_at) AS last_check,
                        url_checks.status_code
                    FROM urls
                    LEFT JOIN url_checks
                        ON url_checks.url_id = urls.id
                    GROUP BY
                        url_checks.url_id,
                        urls.id,
                        urls.name,
                        url_checks.status_code
                    ORDER BY urls.id DESC';
    $getUrls = $connect->prepare($sqlGetUrls);
    $getUrls->execute();
    $urls = $getUrls->fetchAll(\PDO::FETCH_ASSOC);

    $params = [
        'urls' => $urls
    ];

    return $this->get('renderer')->render($response, 'urls.phtml', $params);
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
    $createdAt = Carbon::now();

    $sqlAddUrl = 'INSERT INTO urls (name, created_at) VALUES (:name, :created_at)';
    $addUrl = $connect->prepare($sqlAddUrl);
    $addUrl->bindValue(':name', $urlName);
    $addUrl->bindValue(':created_at', $createdAt);
    $addUrl->execute();

    $sqlGetId = 'SELECT id FROM urls WHERE name = :name';
    $getId = $connect->prepare($sqlGetId);
    $getId->bindValue(':name', $urlName);
    $getId->execute();
    $id = $getId->fetchColumn();
    $url = $router->urlFor('url', ['id' => $id]);
    $this->get('flash')->addMessage('success', 'Страница успешно добавлена');

    return $response->withRedirect($url);
});

$app->get('/urls/{id}', function ($request, $response, array $args) use ($connect) {
    $id = $args['id'];

    $sqlGetLine = 'SELECT * FROM urls WHERE id = :id';
    $getLine = $connect->prepare($sqlGetLine);
    $getLine->bindValue(':id', $id);
    $getLine->execute();
    $urlData = $getLine->fetch(\PDO::FETCH_ASSOC);

    $sqlGetChecks = 'SELECT * FROM url_checks WHERE url_id = :url_id ORDER BY id DESC';
    $getChecks = $connect->prepare($sqlGetChecks);
    $getChecks->bindValue(':url_id', $id);
    $getChecks->execute();
    $checks = $getChecks->fetchAll(\PDO::FETCH_ASSOC);

    $flash = $this->get('flash')->getMessages();
    $status = null;

    if (key($flash) === 'success') {
        $status = 'success';
    }

    if (key($flash) === 'danger') {
        $status = 'danger';
    }
    
    $params = [
        'url' => $urlData['name'],
        'id' => $urlData['id'],
        'createdAt' => $urlData['created_at'],
        'checks' => $checks,
        'flash' => $flash,
        'status' => $status
    ];

    return $this->get('renderer')->render($response, 'url.phtml', $params);
})->setName('url');

$app->post('/urls/{id}/checks', function ($request, $response, array $args) use ($router, $connect) {
    $urlId = $args['id'];
    $url = $router->urlFor('url', ['id' => $urlId]);
    $checkAt = Carbon::now();

    $sqlGetCurrentUrl = 'SELECT name FROM urls WHERE id = :id';
    $getCurrentUrl = $connect->prepare($sqlGetCurrentUrl);
    $getCurrentUrl->bindValue(':id', $urlId);
    $getCurrentUrl->execute();
    $currentUrl = $getCurrentUrl->fetch(\PDO::FETCH_COLUMN);

    $client = new Client();

    try {
        $res = $client->request('GET', $currentUrl);
        $flash = 'Страница успешно проверена';
        $this->get('flash')->addMessage('success', $flash);
    } catch (ConnectException $e) {
        $flash = 'Произошла ошибка при проверке, не удалось подключиться';
        $this->get('flash')->addMessage('danger', $flash);
        return $response->withRedirect($url);
    }

    $statusCode = $res->getStatusCode();
    $document = new Document($currentUrl, true);
    $h1 = optional($document->first('h1'))->text();
    $title = optional($document->first('title'))->text();
    $description = optional($document->first('meta[name="description"]'))->getAttribute('content');

    $sqlAddCheck = 'INSERT INTO url_checks
                        (url_id, created_at, status_code, h1, title, description) VALUES
                        (:url_id, :created_at, :status_code, :h1, :title, :description)';
    $addUrl = $connect->prepare($sqlAddCheck);
    $addUrl->bindValue(':url_id', $urlId);
    $addUrl->bindValue(':created_at', $checkAt);
    $addUrl->bindValue(':status_code', $statusCode);
    $addUrl->bindValue(':h1', $h1);
    $addUrl->bindValue(':title', $title);
    $addUrl->bindValue(':description', $description);
    $addUrl->execute();

    return $response->withRedirect($url);
});

$app->run();