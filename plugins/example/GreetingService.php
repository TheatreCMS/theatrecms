<?php

declare(strict_types=1);

namespace TheatreCMS\Plugin\Example;

class GreetingService
{
    public function __construct(private readonly string $name)
    {
    }

    public function greet(): string
    {
        return "Hello from {$this->name}!";
    }
}
