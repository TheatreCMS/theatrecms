<?php

namespace Clubdeuce\TheatreCMS\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        session_start();

        if (empty($_SESSION['user_id']))
            return (new Response())->withHeader('Location', '/admin/login')->withStatus(302);

        return $handler->handle($request);
    }
}
