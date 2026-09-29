<?php

namespace TheatreCMS\Tests\Unit\Middleware;

use Delight\Auth\Auth;
use TheatreCMS\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddlewareTest extends TestCase
{
    private Auth $auth;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping.');
        }

        // Auth is final, so use a real instance; isLoggedIn() only reads the session.
        $this->auth = new Auth(new \PDO('sqlite::memory:'));
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testRedirectsToLoginWhenNotAuthenticated(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = (new AuthMiddleware($this->auth))->process($request, $handler);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin/login', $response->getHeaderLine('Location'));
    }

    public function testProceedsWhenAuthenticated(): void
    {
        $_SESSION[Auth::SESSION_FIELD_LOGGED_IN] = true;
        $_SESSION[Auth::SESSION_FIELD_USER_ID] = 123;

        $request = $this->createStub(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $expectedResponse = new Response();

        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($expectedResponse);

        $response = (new AuthMiddleware($this->auth))->process($request, $handler);

        $this->assertSame($expectedResponse, $response);
    }
}
