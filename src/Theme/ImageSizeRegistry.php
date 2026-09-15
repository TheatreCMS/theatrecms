<?php

namespace TheatreCMS\Theme;

class ImageSizeRegistry
{
    /**
     * @var array<string, ImageSize>
     */
    private array $sizes = [];

    private static ?self $instance = null;

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The ImageSizeRegistry has not been initialized.');
        }

        return self::$instance;
    }

    public function register(string $name, int $width, int $height, bool $crop = false): void
    {
        $this->sizes[$name] = new ImageSize($name, $width, $height, $crop);
    }

    /**
     * @return array<string, ImageSize>
     */
    public function all(): array
    {
        return $this->sizes;
    }

    public function has(string $name): bool
    {
        return isset($this->sizes[$name]);
    }

    public function get(string $name): ?ImageSize
    {
        return $this->sizes[$name] ?? null;
    }
}
