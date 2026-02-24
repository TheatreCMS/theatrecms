<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Controllers\UsersController;
use Clubdeuce\TheatreCMS\Models\User;
use Clubdeuce\TheatreCMS\Repositories\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;

class UsersControllerTest extends TestCase
{
    /** @var UserRepository|MockObject */
    private $repo;
    /** @var Twig|MockObject */
    private $twig;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(UserRepository::class);
        $this->twig = $this->createMock(Twig::class);
    }

    public function testUpdateReturns400WhenNoBody(): void
    {
        $controller = new UsersController($this->repo, $this->twig);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParsedBody')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);

        $result = $controller->update($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testUpdateReturns404WhenUserNotFound(): void
    {
        $controller = new UsersController($this->repo, $this->twig);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParsedBody')->willReturn(['userId' => 999]);

        $response = $this->createMock(ResponseInterface::class);

        $this->repo->expects($this->once())->method('fetch')->with(999)->willReturn(null);

        $result = $controller->update($request, $response);

        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testUpdateRendersEditWithErrorsWhenPasswordMismatch(): void
    {
        $controller = new UsersController($this->repo, $this->twig);

        $user = new User('old@example.com');
        $user->setUsername('olduser');

        $this->repo->expects($this->once())->method('fetch')->with(1)->willReturn($user);

        // Twig should be called to render edit template
        $this->twig->expects($this->once())->method('render')->with(
            $this->isInstanceOf(ResponseInterface::class),
            'admin/users/edit.html.twig',
            $this->callback(function ($context) {
                return isset($context['errors']) && isset($context['old']) && isset($context['user']);
            })
        )->willReturn($this->createMock(ResponseInterface::class));

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParsedBody')->willReturn([
            'userId' => 1,
            'email' => 'new@example.com',
            'username' => 'newuser',
            'password' => 'abc',
            'password_confirmation' => 'different',
        ]);

        $response = $this->createMock(ResponseInterface::class);

        $result = $controller->update($request, $response);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}

