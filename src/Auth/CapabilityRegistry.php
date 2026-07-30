<?php

namespace TheatreCMS\Auth;

class CapabilityRegistry
{
    /**
     * @var array<string, int[]> capability => Delight\Auth\Role constants that grant it
     */
    private array $capabilities = [];

    private static ?self $instance = null;

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The CapabilityRegistry has not been initialized.');
        }

        return self::$instance;
    }

    /**
     * @param int[] $roles Delight\Auth\Role constants that grant this capability
     */
    public function register(string $capability, array $roles): void
    {
        $this->capabilities[$capability] = $roles;
    }

    /**
     * @return int[] the Delight\Auth\Role constants that grant this capability, or an empty
     *               array if the capability was never registered (fails closed)
     */
    public function rolesFor(string $capability): array
    {
        return $this->capabilities[$capability] ?? [];
    }
}
