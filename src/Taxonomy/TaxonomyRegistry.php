<?php

namespace TheatreCMS\Taxonomy;

use InvalidArgumentException;
use TheatreCMS\Auth\Capability;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Helpers\Args;
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
    private const VALID_OVERRIDES = ['label', 'singularLabel', 'capability', 'multiple', 'urlPrefix', 'hasArchive'];

    /**
     * @param ContentTypeRegistry $contentTypes the content types a taxonomy may apply to; each must be
     *        registered before a taxonomy names it
     * @param UrlPrefixRegistry|null $urlPrefixes prefix claims shared with `ContentTypeRegistry`, so
     *        a taxonomy and a content type can't use the same prefix; a private one if omitted
     */
    public function __construct(
        private readonly ContentTypeRegistry $contentTypes,
        ?UrlPrefixRegistry $urlPrefixes = null,
    ) {
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
     *     singularLabel?: string,
     *     multiple?: bool,
     *     capability?: string,
     *     urlPrefix?: string,
     *     hasArchive?: bool
     * } $args
     * @throws InvalidArgumentException for an invalid or already registered name, an invalid URL
     *        prefix, no content types, an unregistered content type, or a prefix another taxonomy
     *        or content type already uses
     */
    public function register(string $name, array $contentTypes, array $args = []): TaxonomyDefinition
    {
        NamingRules::assertTypeName($name, 'taxonomy');

        if ($this->has($name)) {
            throw new InvalidArgumentException(sprintf('Taxonomy "%s" is already registered.', $name));
        }

        if ($contentTypes === []) {
            throw new InvalidArgumentException(
                sprintf('Taxonomy "%s" must apply to at least one content type.', $name)
            );
        }

        foreach ($contentTypes as $contentType) {
            if (!$this->contentTypes->has($contentType)) {
                throw new InvalidArgumentException(sprintf(
                    'Taxonomy "%s" applies to content type "%s", which is not registered.',
                    $name,
                    $contentType,
                ));
            }
        }

        $args          = Args::withoutUnknownKeys($args, self::VALID_OVERRIDES, 'taxonomy');
        $fallbackLabel = ucwords(str_replace('_', ' ', $name));
        $urlPrefix     = $args['urlPrefix'] ?? str_replace('_', '-', $name);
        $hasArchive    = $args['hasArchive'] ?? true;

        // Every taxonomy claims its prefix, with or without term archives: a prefix belongs to
        // exactly one content type or taxonomy.
        NamingRules::assertUrlPrefix($urlPrefix, sprintf('taxonomy "%s"', $name));
        $this->urlPrefixes->claim($urlPrefix);

        $definition = new TaxonomyDefinition(
            $name,
            array_values($contentTypes),
            $args['label'] ?? $fallbackLabel,
            $args['singularLabel'] ?? $fallbackLabel,
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
