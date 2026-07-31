<?php

namespace TheatreCMS\Tests\Unit\Middleware;

use Delight\Auth\Auth;
use Delight\Auth\Role;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\CapabilityRegistry;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;

class TestRequireCapabilityMiddleware extends TestCase
{
    private Auth $auth;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping.');
        }

        $pdo = new \PDO('sqlite::memory:');
        $schema = file_get_contents(dirname(__DIR__, 3) . '/vendor/delight-im/auth/Database/SQLite.sql');
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }

        $this->auth = new Auth($pdo);
    }

    public function testReturns403WhenCapabilityIsDenied(): void
    {
        $registry = new CapabilityRegistry();
        $registry->register(Role::ADMIN, ['manage_users']);
        $service = new AuthorizationService($this->auth, $registry);

        $_SESSION[Auth::SESSION_FIELD_LOGGED_IN] = true;
        $_SESSION[Auth::SESSION_FIELD_ROLES] = 0;

        $middleware = new RequireCapabilityMiddleware($service, 'manage_users');

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testProceedsWhenCapabilityIsGranted(): void
    {
        $registry = new CapabilityRegistry();
        $registry->register(Role::ADMIN, ['manage_users']);
        $service = new AuthorizationService($this->auth, $registry);

        $_SESSION[Auth::SESSION_FIELD_LOGGED_IN] = true;
        $_SESSION[Auth::SESSION_FIELD_ROLES] = Role::ADMIN;

        $middleware = new RequireCapabilityMiddleware($service, 'manage_users');

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $expectedResponse = new Response();
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($expectedResponse);

        $response = $middleware->process($request, $handler);

        $this->assertSame($expectedResponse, $response);
    }
}
