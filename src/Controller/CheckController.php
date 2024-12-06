<?php

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Carbon\Carbon;
use Slim\Routing\RouteContext;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use DiDom\Document;
use DI\Container;

class CheckController
{
    private object $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function check(Request $request, Response $response, array $args)
    {
        $urlId = $args['id'];

        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor('url', ['id' => $urlId]);
        $checkAt = Carbon::now();

        $sqlGetCurrentUrl = 'SELECT name FROM urls WHERE id = :id';
        $getCurrentUrl = $this->container->get('connect')->prepare($sqlGetCurrentUrl);
        $getCurrentUrl->bindValue(':id', $urlId);
        $getCurrentUrl->execute();
        $currentUrl = $getCurrentUrl->fetch(\PDO::FETCH_COLUMN);

        if ($currentUrl === false) {
            return $this->container->get('view')
                ->render($response, '404.phtml')
                ->withStatus(404);
        }

        $client = new Client();

        try {
            $res = $client->request('GET', $currentUrl);
            $message = 'Страница успешно проверена';
            $this->container->get('flash')->addMessage('success', $message);
        } catch (ConnectException $e) {
            $message = 'Произошла ошибка при проверке, не удалось подключиться';
            $this->container->get('flash')->addMessage('danger', $message);
            return $response->withHeader('Location', $url)
                ->withStatus(302);
        } catch (RequestException $e) {
            $message = 'Проверка была выполнена успешно, но сервер ответил с ошибкой';
            $this->container->get('flash')->addMessage('warning', $message);
            return $this->container->get('view')
                ->render($response, '500.phtml')
                ->withStatus(500);
        }

        $statusCode = $res->getStatusCode();
        $document = new Document($currentUrl, true);
        $h1 = optional($document->first('h1'))->text();
        $title = optional($document->first('title'))->text();
        $description = $document->first('meta[name="description"]::attr(content)');

        $sqlAddCheck = 'INSERT INTO url_checks
                            (url_id, created_at, status_code, h1, title, description) VALUES
                            (:url_id, :created_at, :status_code, :h1, :title, :description)';
        $addUrl = $this->container->get('connect')->prepare($sqlAddCheck);
        $addUrl->bindValue(':url_id', $urlId);
        $addUrl->bindValue(':created_at', $checkAt);
        $addUrl->bindValue(':status_code', $statusCode);
        $addUrl->bindValue(':h1', $h1);
        $addUrl->bindValue(':title', $title);
        $addUrl->bindValue(':description', $description);
        $addUrl->execute();

        return $response->withHeader('Location', $url)
            ->withStatus(302);
    }
}
