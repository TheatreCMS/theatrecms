<?php

namespace TheatreCMS\Tests\Unit;

use Delight\Auth\Auth;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use TheatreCMS\Controllers\UsersController;
use TheatreCMS\Models\User;
use TheatreCMS\Repositories\UserRepository;

#[AllowMockObjectsWithoutExpectations]
class UsersControllerTest extends TestCase
{
    /** @var UserRepository|MockObject */
    private $repo;
    /** @var Twig|MockObject */
    private $twig;
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
        $controller = new UsersController($this->repo, $this->twig, $this->auth);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParsedBody')->willReturn([]);

        $response = new \Slim\Psr7\Response();

        $result = $controller->update($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testUpdateReturns404WhenUserNotFound(): void
    {
        $controller = new UsersController($this->repo, $this->twig, $this->auth);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParsedBody')->willReturn(['userId' => 999]);

        $response = new \Slim\Psr7\Response();

        $this->repo->expects($this->once())->method('fetch')->with(999)->willReturn(null);

        $result = $controller->update($request, $response);

        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testUpdateRendersEditWithErrorsWhenPasswordMismatch(): void
    {
        $controller = new UsersController($this->repo, $this->twig, $this->auth);

        $user = new User(1, 'old@example.com', 'olduser', 0, null);
        $this->loginAs(99);

        $this->repo->expects($this->once())->method('fetch')->with(1)->willReturn($user);
        $this->repo->expects($this->once())->method('findByEmail')->with('new@example.com')->willReturn(null);
        $this->repo->expects($this->once())->method('resolveRoleLabel')->with($user)->willReturn('user');

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

    public function testUpdateKeepsExistingUsername(): void
    {
        $controller = new UsersController($this->repo, $this->twig, $this->auth);

        $user = new User(1, 'old@example.com', 'olduser', 0, null);
        $this->loginAs(99);

        $this->repo->expects($this->once())->method('fetch')->with(1)->willReturn($user);
        $this->repo->expects($this->once())->method('findByEmail')->with('new@example.com')->willReturn(null);
        $this->repo->expects($this->once())->method('updateEmail')->with(1, 'new@example.com');
        $this->repo->expects($this->once())->method('syncRoleByUserId')->with(1, 'user');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParsedBody')->willReturn([
            'userId' => 1,
            'email' => 'new@example.com',
            'username' => 'newuser',
            'role' => 'user',
        ]);
        $request->method('getHeaderLine')->willReturn('');

        $response = new \Slim\Psr7\Response();

        $result = $controller->update($request, $response);

        $this->assertEquals(302, $result->getStatusCode());
        $this->assertEquals('olduser', $user->getUsername());
    }
}
