<?php

namespace TheatreCMS\Theme;

class MenuLocationRegistry
{
    /**
     * @var array<string, string> slug => human label
     */
    private array $locations = [];

    private static ?self $instance = null;

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The MenuLocationRegistry has not been initialized.');
        }

        return self::$instance;
    }

    public function register(string $slug, string $label): void
    {
        $this->locations[$slug] = $label;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->locations;
    }

    public function has(string $slug): bool
    {
        return isset($this->locations[$slug]);
    }

    public function label(string $slug): ?string
    {
        return $this->locations[$slug] ?? null;
    }
}
