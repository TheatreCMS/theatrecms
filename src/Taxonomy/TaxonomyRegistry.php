<?php

namespace TheatreCMS\Taxonomy;

use InvalidArgumentException;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Routing\NamingRules;
use TheatreCMS\Routing\UrlPrefixRegistry;

class TaxonomyRegistry
{
    /**
     * @var array<string, TaxonomyDefinition> name => definition
     */
    private array $taxonomies = [];

    private readonly UrlPrefixRegistry $urlPrefixes;

    private static ?self $instance = null;

    /**
     * @param UrlPrefixRegistry|null $urlPrefixes prefix claims shared with `ContentTypeRegistry`, so
     *        a taxonomy and a content type can't use the same prefix; a private one if omitted
     */
    public function __construct(?UrlPrefixRegistry $urlPrefixes = null)
    {
        $this->urlPrefixes = $urlPrefixes ?? new UrlPrefixRegistry();
    }

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
     * @throws InvalidArgumentException for an invalid name or URL prefix, no content types, or a
     *        prefix another taxonomy or content type already serves routes under
     */
    public function register(string $name, array $contentTypes, array $args = []): TaxonomyDefinition
    {
        NamingRules::assertTypeName($name, 'taxonomy');

        if ($contentTypes === []) {
            throw new InvalidArgumentException(
                sprintf('Taxonomy "%s" must apply to at least one content type.', $name)
            );
        }

        $fallbackLabel = ucwords(str_replace('_', ' ', $name));
        $urlPrefix = $args['url_prefix'] ?? str_replace('_', '-', $name);
        $hasArchive = $args['has_archive'] ?? true;
        $owner = sprintf('taxonomy "%s"', $name);

        // Term archives (`/{prefix}/{term}`) are the only routes a taxonomy serves, so only a
        // taxonomy with archives claims its prefix. The format is checked either way.
        NamingRules::assertUrlPrefix($urlPrefix, $owner);

        if ($hasArchive) {
            $this->urlPrefixes->claim($urlPrefix, $owner);
        }

        $definition = new TaxonomyDefinition(
            $name,
            array_values($contentTypes),
            $args['label'] ?? $fallbackLabel,
            $args['singular_label'] ?? $fallbackLabel,
            $args['multiple'] ?? true,
            $args['capability'] ?? Capability::MANAGE_OPTIONS,
            $urlPrefix,
            $hasArchive,
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
            static fn (TaxonomyDefinition $taxonomy): bool => $taxonomy->appliesTo($contentType)
        );
    }
}
