<?php

namespace TheatreCMS\Routing;

use InvalidArgumentException;

/**
 * Records which content type or taxonomy owns each top-level URL prefix, so no two of them
 * can serve routes under the same `/{prefix}`.
 *
 * `ContentTypeRegistry` and `TaxonomyRegistry` share one instance (wired in
 * `ServiceRegistrar`) and claim each prefix when a type is registered. The order of
 * registration doesn't matter: whichever claims a taken prefix second gets the exception.
 */
final class UrlPrefixRegistry
{
    /**
     * @var array<string, string> URL prefix => owner
     */
    private array $owners = [];

    /**
     * Validates the prefix (see `NamingRules::assertUrlPrefix()`) and records it for the owner.
     * Claiming a prefix the same owner already holds is a no-op, so re-registering a type
     * keeps its prefix.
     *
     * @param string $owner a description that identifies the claimant, e.g. `content type "seasons"`
     *        or `taxonomy "genre"`; it also appears in error messages
     * @throws InvalidArgumentException for an invalid prefix, or one another owner already holds
     */
    public function claim(string $urlPrefix, string $owner): void
    {
        NamingRules::assertUrlPrefix($urlPrefix, $owner);

        $existing = $this->owners[$urlPrefix] ?? null;

        if ($existing !== null && $existing !== $owner) {
            throw new InvalidArgumentException(sprintf(
                'URL prefix "%s" for %s is already used by %s.',
                $urlPrefix,
                $owner,
                $existing
            ));
        }

        $this->owners[$urlPrefix] = $owner;
    }
}
