<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TheatreCMS\Controllers\LoginController;
use TheatreCMS\Repositories\UserRepository;
use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Delight\Auth\Auth;

class LoginControllerTest extends TestCase
{
    private UserRepository&Stub $repository;
    private Twig|MockObject $twig;
    private Auth $auth;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping.');
        }

        $this->repository = $this->createStub(UserRepository::class);
        $this->twig       = $this->createMock(Twig::class);

        // Auth is final and cannot be mocked, so back a real instance with an in-memory database.
        $pdo = new \PDO('sqlite::memory:');
        $schema = file_get_contents(dirname(__DIR__, 2) . '/vendor/delight-im/auth/Database/SQLite.sql');
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }
        $this->auth = new Auth($pdo);
    }

    public function testLoginPassesCsrfTokensToTemplate(): void
    {
        $controller = new LoginController($this->repository, $this->twig, $this->auth);

        $request = $this->createStub(Request::class);
        $request->method('getAttribute')
            ->willReturnMap([
                [LoginController::CSRF_NAME_KEY, null, 'csrf_abc123'],
                [LoginController::CSRF_VALUE_KEY, null, 'token_xyz789'],
            ]);

        $response = $this->createStub(Response::class);

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                $this->isInstanceOf(Response::class),
                'admin/login.html.twig',
                $this->callback(function (array $context): bool {
                    return $context['csrf_name_key'] === LoginController::CSRF_NAME_KEY
                        && $context['csrf_value_key'] === LoginController::CSRF_VALUE_KEY
                        && $context['csrf_name'] === 'csrf_abc123'
                        && $context['csrf_value'] === 'token_xyz789';
                })
            )
            ->willReturn($response);

        $result = $controller->login($request, $response);

        $this->assertSame($response, $result);
    }

    public function testLoginRendersTemplateWithNullTokensWhenAttributesAbsent(): void
    {
        $controller = new LoginController($this->repository, $this->twig, $this->auth);

        $request = $this->createStub(Request::class);
        $request->method('getAttribute')->willReturn(null);

        $response = $this->createStub(Response::class);

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                $this->isInstanceOf(Response::class),
                'admin/login.html.twig',
                $this->callback(function (array $context): bool {
                    return array_key_exists('csrf_name', $context)
                        && array_key_exists('csrf_value', $context)
                        && $context['csrf_name'] === null
                        && $context['csrf_value'] === null;
                })
            )
            ->willReturn($response);

        $result = $controller->login($request, $response);

        $this->assertSame($response, $result);
    }
}
