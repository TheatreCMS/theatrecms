<?php

namespace TheatreCMS\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use TheatreCMS\Auth\AuthorizationService;

readonly class RequireCapabilityMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthorizationService $authorization, private string $capability)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->authorization->can($this->capability)) {
            return $handler->handle($request);
        }

        $response = new Response();
        $response->getBody()->write('Forbidden');
        return $response->withStatus(403);
    }
}
