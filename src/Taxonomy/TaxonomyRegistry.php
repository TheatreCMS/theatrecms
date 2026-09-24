<?php

namespace TheatreCMS\Taxonomy;

use InvalidArgumentException;
use TheatreCMS\Auth\Capability;

class TaxonomyRegistry
{
    /**
     * @var array<string, TaxonomyDefinition> name => definition
     */
    private array $taxonomies = [];

    private static ?self $instance = null;

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The TaxonomyRegistry has not been initialized.');
        }

        return self::$instance;
    }

    /**
     * @param string[] $contentTypes
     * @param array{
     *     label?: string,
     *     singular_label?: string,
     *     multiple?: bool,
     *     capability?: string,
     *     url_prefix?: string,
     *     has_archive?: bool
     * } $args
     */
    public function register(string $name, array $contentTypes, array $args = []): TaxonomyDefinition
    {
        if (!preg_match('/^[a-z0-9_]{1,32}$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid taxonomy name "%s": use 1-32 lowercase letters, digits, or underscores.',
                $name
            ));
        }

        if ($contentTypes === []) {
            throw new InvalidArgumentException(
                sprintf('Taxonomy "%s" must apply to at least one content type.', $name)
            );
        }

        $fallbackLabel = ucwords(str_replace('_', ' ', $name));
        $urlPrefix = $this->validateUrlPrefix($name, $args['url_prefix'] ?? str_replace('_', '-', $name));

        $definition = new TaxonomyDefinition(
            $name,
            array_values($contentTypes),
            $args['label'] ?? $fallbackLabel,
            $args['singular_label'] ?? $fallbackLabel,
            $args['multiple'] ?? true,
            $args['capability'] ?? Capability::MANAGE_OPTIONS,
            $urlPrefix,
            $args['has_archive'] ?? true,
        );

        $this->taxonomies[$name] = $definition;

        return $definition;
    }

    /**
     * @return array<string, TaxonomyDefinition>
     */
    public function all(): array
    {
        return $this->taxonomies;
    }

    public function has(string $name): bool
    {
        return isset($this->taxonomies[$name]);
    }

    public function get(string $name): ?TaxonomyDefinition
    {
        return $this->taxonomies[$name] ?? null;
    }

    /**
     * The taxonomy whose term archives are served under this URL path segment, if any.
     */
    public function findByUrlPrefix(string $urlPrefix): ?TaxonomyDefinition
    {
        foreach ($this->taxonomies as $taxonomy) {
            if ($taxonomy->urlPrefix === $urlPrefix) {
                return $taxonomy;
            }
        }

        return null;
    }

    /**
     * Like get(), but fails closed for an unregistered taxonomy.
     */
    public function require(string $name): TaxonomyDefinition
    {
        $definition = $this->get($name);

        if ($definition === null) {
            throw new InvalidArgumentException(sprintf('Unknown taxonomy "%s".', $name));
        }

        return $definition;
    }

    /**
     * @return array<string, TaxonomyDefinition> taxonomies that can be attached to the given content type
     */
    public function forContentType(string $contentType): array
    {
        return array_filter(
            $this->taxonomies,
            static fn(TaxonomyDefinition $taxonomy): bool => $taxonomy->appliesTo($contentType)
        );
    }

    /**
     * Term archives are top-level routes (`/{prefix}/{term}`), so a prefix must be a single
     * URL-safe path segment, can't shadow the admin, and can't be shared with another taxonomy.
     * Re-registering a taxonomy under its own name keeps its prefix.
     */
    private function validateUrlPrefix(string $name, string $urlPrefix): string
    {
        if (!preg_match('/^[a-z0-9-]+$/', $urlPrefix)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid URL prefix "%s" for taxonomy "%s": use lowercase letters, digits, or hyphens.',
                $urlPrefix,
                $name
            ));
        }

        if ($urlPrefix === 'admin') {
            throw new InvalidArgumentException(
                sprintf('Taxonomy "%s" cannot use the reserved URL prefix "admin".', $name)
            );
        }

        $existing = $this->findByUrlPrefix($urlPrefix);
        if ($existing !== null && $existing->name !== $name) {
            throw new InvalidArgumentException(sprintf(
                'Taxonomy "%s" cannot use URL prefix "%s": taxonomy "%s" already uses it.',
                $name,
                $urlPrefix,
                $existing->name
            ));
        }

        return $urlPrefix;
    }
}
