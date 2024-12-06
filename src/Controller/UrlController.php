<?php

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Valitron\Validator;
use Carbon\Carbon;
use Slim\Routing\RouteContext;
use DI\Container;

class UrlController
{
    private mixed $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function showUrls(Request $request, Response $response)
    {
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

        $getUrls = $this->container->get('connect')->prepare($sqlGetUrls);
        $getUrls->execute();
        $urls = $getUrls->fetchAll();

        $params = [
            'urls' => $urls
        ];

        return $this->container->get('view')
            ->render($response, 'urls.phtml', $params);
    }

    public function store(Request $request, Response $response)
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            $dataRequest = $parsedBody['url'];
        }

        $urlErrors = new Validator($dataRequest);
        $urlErrors->rule('required', 'name');
        $valid = $urlErrors->validate();
        if ($valid === false) {
            $message = 'URL не должен быть пустым';
            $params = ['message' => $message];
            return $this->container->get('view')
                ->render($response, 'home.phtml', $params)
                ->withStatus(422);
        }

        $urlErrors->rule('lengthMax', 'name', 255);
        $valid = $urlErrors->validate();
        if ($valid === false) {
            $message = 'Некорректный URL';
            $params = ['message' => $message];
            return $this->container->get('view')
                ->render($response, 'home.phtml', $params)
                ->withStatus(422);
        }

        $urlErrors->rule('url', 'name');
        $valid = $urlErrors->validate();
        if ($valid === false) {
            $message = 'Некорректный URL';
            $params = ['message' => $message];
            return $this->container->get('view')
                ->render($response, 'home.phtml', $params)
                ->withStatus(422);
        }

        $urlData = parse_url(mb_strtolower($dataRequest['name']));
        $urlName = "{$urlData['scheme']}://{$urlData['host']}";
        $createdAt = Carbon::now();

        $sqlGetUrlsName = 'SELECT name FROM urls';
        $getUrlsName = $this->container->get('connect')->prepare($sqlGetUrlsName);
        $getUrlsName->execute();
        $urls = $getUrlsName->fetchAll(\PDO::FETCH_COLUMN);

        if (in_array($urlName, $urls)) {
            $this->container->get('flash')->addMessage('success', 'Страница уже существует');
        } else {
            $sqlAddUrl = 'INSERT INTO urls
                        (name, created_at) VALUES
                        (:name, :created_at)';
            $addUrl = $this->container->get('connect')->prepare($sqlAddUrl);
            $addUrl->bindValue(':name', $urlName);
            $addUrl->bindValue(':created_at', $createdAt);
            $addUrl->execute();
            $this->container->get('flash')->addMessage('success', 'Страница успешно добавлена');
        }

        $sqlGetId = 'SELECT id FROM urls WHERE name = :name';
            $getId = $this->container->get('connect')->prepare($sqlGetId);
            $getId->bindValue(':name', $urlName);
            $getId->execute();
            $id = $getId->fetchColumn();

            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $url = $routeParser->urlFor('url', ['id' => $id]);

            return $response->withHeader('Location', $url)
                ->withStatus(302);
    }

    public function showUrl(Request $request, Response $response, array $args)
    {
        $id = $args['id'];

        if (ctype_digit($id) === false) {
            return $this->container->get('view')
                ->render($response, '404.phtml')
                ->withStatus(404);
        }

        $sqlGetLine = 'SELECT * FROM urls WHERE id = :id';
        $getLine = $this->container->get('connect')->prepare($sqlGetLine);
        $getLine->bindValue(':id', $id);
        $getLine->execute();
        $urlData = $getLine->fetch();

        if ($urlData === false) {
            return $this->container->get('view')
                ->render($response, '404.phtml')
                ->withStatus(404);
        }

        $sqlGetChecks = 'SELECT * FROM url_checks WHERE url_id = :url_id ORDER BY id DESC';
        $getChecks = $this->container->get('connect')->prepare($sqlGetChecks);
        $getChecks->bindValue(':url_id', $id);
        $getChecks->execute();
        $checks = $getChecks->fetchAll();

        $flash = $this->container->get('flash')->getMessages();
        $status = null;

        if (key($flash) === 'success') {
            $status = 'success';
        }

        if (key($flash) === 'danger') {
            $status = 'danger';
        }

        if (key($flash) === 'warning') {
            $status = 'warning';
        }

        $params = [
            'url' => $urlData['name'],
            'id' => $urlData['id'],
            'createdAt' => $urlData['created_at'],
            'checks' => $checks,
            'flash' => $flash,
            'status' => $status
        ];

        return $this->container->get('view')
            ->render($response, 'url.phtml', $params);
    }
}
