<?php

namespace TheatreCMS\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Auth\CapabilityRegistry;

class CapabilityRegistryTest extends TestCase
{
    public function testRolesForReturnsRegisteredRoles(): void
    {
        $registry = new CapabilityRegistry();
        $registry->register('manage_users', [1]);

        $this->assertSame([1], $registry->rolesFor('manage_users'));
    }

    public function testRolesForReturnsEmptyArrayWhenUnregistered(): void
    {
        $registry = new CapabilityRegistry();

        $this->assertSame([], $registry->rolesFor('unknown_capability'));
    }

    public function testGetInstanceThrowsWhenNotInitialized(): void
    {
        $reflection = new \ReflectionProperty(CapabilityRegistry::class, 'instance');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        CapabilityRegistry::getInstance();
    }

    public function testSetInstanceAndGetInstance(): void
    {
        $registry = new CapabilityRegistry();
        CapabilityRegistry::setInstance($registry);

        $this->assertSame($registry, CapabilityRegistry::getInstance());
    }
}
