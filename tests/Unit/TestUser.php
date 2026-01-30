<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\User;
use Clubdeuce\TheatreCMS\Tests\Includes\TestCase;

/**
 * Class TestUser
 *
 * @package Clubdeuce\TheatreCMS\Tests\Unit
 * @coversDefaultClass \Clubdeuce\TheatreCMS\Models\User
 */
class TestUser extends TestCase
{
    public function testId()
    {
        $user = new User('john@doe.com');
        $this->assertEquals(0, $user->getId());
    }

    public function testUserEmail()
    {
        $user = new User('john@doe.com');

        $this->assertEquals('john@doe.com', $user->getEmail(), 'Error setting user email on construction');
        $user->setEmail('jane@doe.com');
        $this->assertEquals('jane@doe.com', $user->getEmail(), 'Error updating user email');

        $this->expectException(\Exception::class);
        $user->setEmail('john');
    }

    public function testRegisteredDtm()
    {
        $user = new User('john@doe.com');
        $dtm = new \DateTimeImmutable('2024-01-01 12:00:00');
        $user->setRegisteredDtm($dtm);
        $this->assertEquals($dtm, $user->getRegisteredDtm(), 'Error setting registered datetime');

    }
}