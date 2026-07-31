<?php

namespace TheatreCMS\Tests\Unit;

use TheatreCMS\Models\User;
use TheatreCMS\Tests\Includes\TestCase;

/**
 * Class TestUser
 *
 * @package TheatreCMS\Tests\Unit
 * @coversDefaultClass \TheatreCMS\Models\User
 */
class TestUser extends TestCase
{
    public function testUsernameGetSet(): void
    {
        $u = new User('alice@example.com');
        $u->setUsername('alice');
        $this->assertSame('alice', $u->getUsername());

        $u->setUsername('  bob  ');
        $this->assertSame('bob', $u->getUsername());
    }

    public function testUsernameEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $u = new User('a@b.com');
        $u->setUsername('   ');
    }

    public function testEmailGetSetAndValidation(): void
    {
        $u = new User('initial@example.com');
        $this->assertSame('initial@example.com', $u->getEmail());

        $u->setEmail('john.doe@example.com');
        $this->assertSame('john.doe@example.com', $u->getEmail());

        $this->expectException(\InvalidArgumentException::class);
        $u->setEmail('not-an-email');
    }

    public function testPasswordGetSet(): void
    {
        $u = new User('p@example.com');
        $u->setPassword('s3cr3t');
        $this->assertSame('s3cr3t', $u->getPassword());
    }

    public function testStatusVerifiedResettable(): void
    {
        $u = new User('s@example.com');
        $u->setStatus(true);
        $this->assertTrue($u->getStatus());

        $u->setVerified(true);
        $this->assertTrue($u->isVerified());

        $u->setResettable(false);
        $this->assertFalse($u->isResettable());
    }

    public function testRolesMaskRegisteredLastLoginForceLogout(): void
    {
        $u = new User('r@example.com');
        $u->setRolesMask(7);
        $this->assertSame(7, $u->getRolesMask());

        $u->setRegistered(1234567890);
        $this->assertSame(1234567890, $u->getRegistered());

        $u->setLastLogin(555);
        $this->assertSame(555, $u->getLastLogin());

        $u->setForceLogout(1);
        $this->assertSame(1, $u->getForceLogout());
    }

}