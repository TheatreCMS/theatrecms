<?php

namespace TheatreCMS\Tests\Includes;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;

/**
 * Helpers for controller tests: a Twig mock that records the last rendered template and its
 * data, and a request builder with optional parsed body, query params and HX-Request header.
 */
trait RendersControllerViews
{
    /** @var array{template: string, data: array<string, mixed>}|null */
    protected ?array $rendered = null;

    protected function recordingTwig(): Twig
    {
        $twig = $this->createMock(Twig::class);
        $twig->method('render')->willReturnCallback(function ($response, string $template, array $data = []) {
            $this->rendered = ['template' => $template, 'data' => $data];
            $response->getBody()->write($template);
            return $response;
        });
        $twig->method('fetch')->willReturnCallback(function (string $template, array $data = []) {
            $this->rendered = ['template' => $template, 'data' => $data];
            return '<div>' . $template . '</div>';
        });

        return $twig;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    protected function request(
        string $method,
        array $body = [],
        bool $htmx = false,
        array $query = [],
        string $uri = '/admin'
    ): ServerRequestInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri)
            ->withParsedBody($body)
            ->withQueryParams($query);

        return $htmx ? $request->withHeader('HX-Request', 'true') : $request;
    }
}
