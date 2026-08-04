<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\User;

class TestUser extends TestCase
{
    public function testExposesAuthUserData(): void
    {
        $user = new User(42, 'alice@example.com', 'alice', 7, 1234567890);

        $this->assertSame(42, $user->getId());
        $this->assertSame('alice@example.com', $user->getEmail());
        $this->assertSame('alice', $user->getUsername());
        $this->assertSame(7, $user->getRolesMask());
        $this->assertSame(1234567890, $user->getLastLogin());
    }

    public function testNullableAuthUserDataHasDisplayDefaults(): void
    {
        $user = new User(1, 'user@example.com', null, 0, null);

        $this->assertSame('', $user->getUsername());
        $this->assertNull($user->getLastLogin());
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User(1, 'not-an-email', null, 0, null);
    }
}
