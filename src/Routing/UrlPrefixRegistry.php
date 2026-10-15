<?php

namespace TheatreCMS\Routing;

use InvalidArgumentException;

/**
 * Records every top-level URL prefix in use, so no two content types or taxonomies can serve
 * routes under the same `/{prefix}`.
 *
 * `ContentTypeRegistry` and `TaxonomyRegistry` share one instance (wired in
 * `ServiceRegistrar`) and claim each prefix when a type is registered. A prefix can be claimed
 * once; whichever registration claims it second gets the exception. Callers validate the prefix
 * (`NamingRules::assertUrlPrefix()`) before claiming it.
 */
final class UrlPrefixRegistry
{
    /**
     * @var array<string, true> URL prefix => true
     */
    private array $claimed = [];

    /**
     * @throws InvalidArgumentException if the prefix is already claimed
     */
    public function claim(string $urlPrefix): void
    {
        if (isset($this->claimed[$urlPrefix])) {
            throw new InvalidArgumentException(sprintf('URL prefix "%s" is already registered.', $urlPrefix));
        }

        $this->claimed[$urlPrefix] = true;
    }
}
