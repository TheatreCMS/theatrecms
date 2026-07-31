<?php

namespace TheatreCMS\Auth;

class CapabilityRegistry
{
    /**
     * @var array<int, string[]> Delight\Auth\Role constant => capabilities it grants
     */
    private array $roleCapabilities = [];

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
     * @param string[] $capabilities capabilities granted to this Delight\Auth\Role constant
     */
    public function register(int $role, array $capabilities): void
    {
        $this->roleCapabilities[$role] = $capabilities;
    }

    /**
     * @return string[] the capabilities granted to this Delight\Auth\Role constant, or an empty
     *                   array if the role was never registered
     */
    public function capabilitiesFor(int $role): array
    {
        return $this->roleCapabilities[$role] ?? [];
    }

    /**
     * @return int[] the Delight\Auth\Role constants that grant this capability, or an empty
     *               array if the capability was never registered (fails closed)
     */
    public function rolesFor(string $capability): array
    {
        $roles = [];
        foreach ($this->roleCapabilities as $role => $capabilities) {
            if (in_array($capability, $capabilities, true)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
