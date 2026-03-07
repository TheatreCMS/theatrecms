<?php

namespace TheatreCMS\Theme;

class HookManager
{
    /**
     * @var array<string, array<int, callable[]>>
     */
    private array $filters = [];

    private static ?self $instance = null;

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The HookManager has not been initialized.');
        }

        return self::$instance;
    }

    public function addFilter(string $tag, callable $callback, int $priority = 10): void
    {
        if (!isset($this->filters[$tag])) {
            $this->filters[$tag] = [];
        }

        if (!isset($this->filters[$tag][$priority])) {
            $this->filters[$tag][$priority] = [];
        }

        $this->filters[$tag][$priority][] = $callback;
    }

    /**
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    public function applyFilters(string $tag, mixed $value, mixed ...$args): mixed
    {
        if (!isset($this->filters[$tag])) {
            return $value;
        }

        $callbacks = $this->filters[$tag];
        ksort($callbacks);

        foreach ($callbacks as $priority => $handlers) {
            foreach ($handlers as $handler) {
                $value = $handler($value, ...$args);
            }
        }

        return $value;
    }
}
