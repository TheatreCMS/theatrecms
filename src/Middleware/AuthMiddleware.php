<?php

namespace TheatreCMS\Middleware;

use Delight\Auth\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

readonly class AuthMiddleware implements MiddlewareInterface
{

    public function __construct(private Auth $auth)
    {
    }


    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->auth->isLoggedIn())
            return $handler->handle($request);


        return (new Response())->withHeader('Location', '/admin/login')->withStatus(302);
    }
}
