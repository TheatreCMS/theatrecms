<?php

namespace TheatreCMS\Tests\Unit;

use Delight\Auth\Auth;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Controllers\ProfileController;
use TheatreCMS\Models\User;
use TheatreCMS\Repositories\UserRepository;

#[AllowMockObjectsWithoutExpectations]
class ProfileControllerTest extends TestCase
{
    private UserRepository|MockObject $repo;
    private Twig|MockObject $twig;
    private Auth $auth;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping.');
        }

        $this->repo = $this->createMock(UserRepository::class);
        $this->twig = $this->createMock(Twig::class);

        $pdo = new \PDO('sqlite::memory:');
        $schema = file_get_contents(dirname(__DIR__, 2) . '/vendor/delight-im/auth/Database/SQLite.sql');
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }
        $this->auth = new Auth($pdo);
    }

    private function loginAs(int $userId): void
    {
        $_SESSION[Auth::SESSION_FIELD_LOGGED_IN] = true;
        $_SESSION[Auth::SESSION_FIELD_USER_ID] = $userId;
    }

    public function testUpdateReturns400WhenNoBody(): void
    {
        $controller = new ProfileController($this->repo, $this->twig, $this->auth);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([]);

        $response = new \Slim\Psr7\Response();

        $result = $controller->update($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testUpdateReturns404WhenCurrentUserNotFound(): void
    {
        $userId = $this->auth->admin()->createUserWithUniqueUsername('a@example.com', 'password123', 'auser');
        $this->loginAs($userId);

        $this->repo->expects($this->once())->method('fetch')->with($userId)->willReturn(null);

        $controller = new ProfileController($this->repo, $this->twig, $this->auth);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn(['email' => 'x@example.com']);

        $response = new \Slim\Psr7\Response();

        $result = $controller->update($request, $response);

        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testUpdateRendersEditWithErrorsWhenPasswordMismatch(): void
    {
        $userId = $this->auth->admin()->createUserWithUniqueUsername('old@example.com', 'password123', 'olduser');
        $this->loginAs($userId);

        $user = new User('old@example.com');
        $user->setUsername('olduser');

        $this->repo->expects($this->once())->method('fetch')->with($userId)->willReturn($user);

        $this->twig->expects($this->once())->method('render')->with(
            $this->isInstanceOf(Response::class),
            'admin/profile/edit.html.twig',
            $this->callback(function ($context) {
                return isset($context['errors']['password_confirmation'])
                    && isset($context['old'])
                    && isset($context['user']);
            })
        )->willReturn($this->createMock(Response::class));

        $controller = new ProfileController($this->repo, $this->twig, $this->auth);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'email' => 'old@example.com',
            'current_password' => 'password123',
            'password' => 'abc12345',
            'password_confirmation' => 'different',
        ]);

        $response = $this->createMock(Response::class);

        $result = $controller->update($request, $response);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testUpdateSavesDetailsWithoutChangingPassword(): void
    {
        $userId = $this->auth->admin()->createUserWithUniqueUsername('old@example.com', 'password123', 'olduser');
        $this->loginAs($userId);

        $user = new User('old@example.com');
        $user->setUsername('olduser');

        $this->repo->expects($this->once())->method('fetch')->with($userId)->willReturn($user);
        $this->repo->expects($this->once())->method('update')->with($user);

        $controller = new ProfileController($this->repo, $this->twig, $this->auth);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'email' => 'new@example.com',
            'username' => 'newuser',
        ]);
        $request->method('getHeaderLine')->willReturn('');

        $response = new \Slim\Psr7\Response();

        $result = $controller->update($request, $response);

        $this->assertEquals(302, $result->getStatusCode());
        $this->assertEquals('new@example.com', $user->getEmail());
        $this->assertEquals('olduser', $user->getUsername());
    }

    public function testUpdateRejectsWrongCurrentPassword(): void
    {
        $userId = $this->auth->admin()->createUserWithUniqueUsername('old@example.com', 'password123', 'olduser');
        $this->loginAs($userId);

        $user = new User('old@example.com');
        $user->setUsername('olduser');

        $this->repo->method('fetch')->with($userId)->willReturn($user);

        $this->twig->expects($this->once())->method('render')->with(
            $this->isInstanceOf(Response::class),
            'admin/profile/edit.html.twig',
            $this->callback(function ($context) {
                return ($context['errors']['current_password'] ?? null) === 'Your current password is incorrect.';
            })
        )->willReturn($this->createMock(Response::class));

        $controller = new ProfileController($this->repo, $this->twig, $this->auth);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'email' => 'old@example.com',
            'current_password' => 'wrong-password',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ]);
        $request->method('getHeaderLine')->willReturn('');

        $response = $this->createMock(Response::class);

        $controller->update($request, $response);
    }
}
