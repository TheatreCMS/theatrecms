<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Auth\AuthorizationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CapabilityExtension extends AbstractExtension
{
    public function __construct(private readonly AuthorizationService $authorization)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('can', [$this, 'can']),
        ];
    }

    public function can(string $capability): bool
    {
        return $this->authorization->can($capability);
    }
}
