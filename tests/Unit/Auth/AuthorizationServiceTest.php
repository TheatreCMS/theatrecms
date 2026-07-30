<?php

namespace TheatreCMS\Tests\Unit\Auth;

use Delight\Auth\Auth;
use Delight\Auth\Role;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\CapabilityRegistry;

class AuthorizationServiceTest extends TestCase
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

    public function testCanIsFalseWhenCapabilityIsUnregistered(): void
    {
        $registry = new CapabilityRegistry();
        $service = new AuthorizationService($this->auth, $registry);

        $this->assertFalse($service->can('manage_users'));
    }

    public function testCanIsTrueWhenCurrentUserHasGrantingRole(): void
    {
        $registry = new CapabilityRegistry();
        $registry->register('manage_users', [Role::ADMIN]);
        $service = new AuthorizationService($this->auth, $registry);

        $_SESSION[Auth::SESSION_FIELD_LOGGED_IN] = true;
        $_SESSION[Auth::SESSION_FIELD_ROLES] = Role::ADMIN;

        $this->assertTrue($service->can('manage_users'));
    }

    public function testCanIsFalseWhenCurrentUserLacksGrantingRole(): void
    {
        $registry = new CapabilityRegistry();
        $registry->register('manage_users', [Role::ADMIN]);
        $service = new AuthorizationService($this->auth, $registry);

        $_SESSION[Auth::SESSION_FIELD_LOGGED_IN] = true;
        $_SESSION[Auth::SESSION_FIELD_ROLES] = 0;

        $this->assertFalse($service->can('manage_users'));
    }

    public function testUserCanChecksAnArbitraryUserById(): void
    {
        $registry = new CapabilityRegistry();
        $registry->register('manage_users', [Role::ADMIN]);
        $service = new AuthorizationService($this->auth, $registry);

        $adminId = $this->auth->admin()->createUserWithUniqueUsername('admin@example.com', 'password123', 'admin');
        $this->auth->admin()->addRoleForUserById($adminId, Role::ADMIN);

        $userId = $this->auth->admin()->createUserWithUniqueUsername('user@example.com', 'password123', 'user');

        $this->assertTrue($service->userCan($adminId, 'manage_users'));
        $this->assertFalse($service->userCan($userId, 'manage_users'));
    }
}
