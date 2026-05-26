<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TheatreCMS\Controllers\LoginController;
use TheatreCMS\Repositories\UserRepository;
use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Delight\Auth\Auth;

class LoginControllerTest extends TestCase
{
    private UserRepository|MockObject $repository;
    private Twig|MockObject $twig;
    private Auth|MockObject $auth;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepository::class);
        $this->twig       = $this->createMock(Twig::class);
        $this->auth       = $this->createMock(Auth::class);
    }

    public function testLoginPassesCsrfTokensToTemplate(): void
    {
        $controller = new LoginController($this->repository, $this->twig, $this->auth);

        $request = $this->createMock(Request::class);
        $request->method('getAttribute')
            ->willReturnMap([
                [LoginController::CSRF_NAME_KEY, null, 'csrf_abc123'],
                [LoginController::CSRF_VALUE_KEY, null, 'token_xyz789'],
            ]);

        $response = $this->createMock(Response::class);

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

        $request = $this->createMock(Request::class);
        $request->method('getAttribute')->willReturn(null);

        $response = $this->createMock(Response::class);

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
