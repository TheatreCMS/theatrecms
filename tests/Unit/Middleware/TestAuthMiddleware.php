<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit\Middleware;

use Clubdeuce\TheatreCMS\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class TestAuthMiddleware extends TestCase
{
    public function testRedirectsToLoginWhenNotAuthenticated(): void
    {
        // Mock session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $middleware = new AuthMiddleware();
        $response = $middleware->process($request, $handler);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin/login', $response->getHeaderLine('Location'));
    }

    public function testProceedsWhenAuthenticated(): void
    {
        $_SESSION['user_id'] = 123;

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $expectedResponse = new Response();

        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($expectedResponse);

        $middleware = new AuthMiddleware();
        $response = $middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $response);
    }
}
