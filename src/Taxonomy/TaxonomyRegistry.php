<?php

namespace TheatreCMS\Taxonomy;

use InvalidArgumentException;

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
     * @param array{label?: string, singular_label?: string, multiple?: bool} $args
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

        $definition = new TaxonomyDefinition(
            $name,
            array_values($contentTypes),
            $args['label'] ?? $fallbackLabel,
            $args['singular_label'] ?? $fallbackLabel,
            $args['multiple'] ?? true,
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
}
