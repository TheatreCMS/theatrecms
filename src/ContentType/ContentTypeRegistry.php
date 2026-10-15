<?php

namespace TheatreCMS\ContentType;

use InvalidArgumentException;
use RuntimeException;
use TheatreCMS\Helpers\Args;
use TheatreCMS\Routing\NamingRules;
use TheatreCMS\Routing\UrlPrefixRegistry;

/**
 * Holds a definition (see `ContentTypeDefinition`) for every content type: its URL path
 * segment, display labels and whether it has an archive page.
 *
 * The built-in types are seeded from `BUILT_IN`, and a site can override their prefix,
 * labels or archive flag via the `content_types` key in `app/config.yaml`. For example, a
 * theatre that calls its season listing "Shows" can serve it under `/shows` with "Shows"
 * as the label everywhere (URLs, menu links, permalinks and page `<title>`s), without any
 * code or template changes:
 *
 *     content_types:
 *         seasons:
 *             urlPrefix: shows
 *             label: Shows
 *
 * A content type's archive (listing) page is enabled by default; `hasArchive: false`
 * disables it (its frontend route 404s) without affecting single-entity routes, e.g. a
 * site can turn off `/seasons` while keeping `/seasons/{slug}` working.
 */
class ContentTypeRegistry
{
    /**
     * Keys a `content_types` config override may set, named as ContentTypeDefinition's
     * constructor parameters so a validated override can be spread straight into it.
     *
     * @var list<string>
     */
    private const VALID_OVERRIDES = [
        'label',
        'singularLabel',
        'urlPrefix',
        'hasArchive',
        'public',
        'supports',
        'capability',
    ];

    /**
     * Built-in content types: key => [URL prefix, label, singular label].
     *
     * @var array<string, array{string, string, string}>
     */
    private const BUILT_IN = [
        'seasons'     => ['label' => 'Seasons', 'singularLabel' => 'Season'],
        'productions' => ['label' => 'Productions', 'singularLabel' => 'Production'],
        'people'      => ['label' => 'People', 'singularLabel' => 'Person'],
        'works'       => ['label' => 'Works', 'singularLabel' => 'Work'],
        'posts'       => ['label' => 'Posts', 'singularLabel' => 'Post'],
    ];

    /**
     * @var array<string, ContentTypeDefinition>
     */
    private array $contentTypes = [];
    private readonly array $overrides;

    private readonly UrlPrefixRegistry $urlPrefixes;

    private static ?ContentTypeRegistry $instance = null;

    /**
     * @param array<string, mixed> $overrides content type => override map from config, e.g.
     *        `['seasons' => ['urlPrefix' => 'shows', 'label' => 'Shows', 'hasArchive' => false]]`.
     *        Allowed keys are listed in VALID_OVERRIDES.
     * @param UrlPrefixRegistry|null $urlPrefixes prefix claims shared with `TaxonomyRegistry`, so
     *        a content type and a taxonomy can't use the same prefix; a private one if omitted
     * @throws InvalidArgumentException for an override that isn't a map, an unknown key, or a
     *        built-in's prefix that is invalid or already claimed
     */
    public function __construct(array $overrides = [], ?UrlPrefixRegistry $urlPrefixes = null)
    {
        $this->urlPrefixes = $urlPrefixes ?? new UrlPrefixRegistry();

        $this->overrides = $this->parseOverrides($overrides);
        $this->registerBuiltIn($overrides);
    }

    public static function setInstance(ContentTypeRegistry $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): ContentTypeRegistry
    {
        if (self::$instance === null) {
            throw new RuntimeException('The ContentTypeRegistry has not been initialized.');
        }

        return self::$instance;
    }

    /**
     * Returns the URL path segment (no leading/trailing slash) for a content type.
     * Unknown types pass through unchanged, so new/custom types work without registration.
     */
    public function prefix(string $type): string
    {
        return $this->contentTypes[$type]->urlPrefix ?? $type;
    }

    /**
     * Returns the display label (e.g. for page titles and menu links) for a content type.
     * Unknown types fall back to a capitalized version of the type key.
     */
    public function label(string $type): string
    {
        return $this->contentTypes[$type]->label ?? ucfirst($type);
    }

    /**
     * Whether a content type's archive (listing) page should be served. Defaults to true
     * for every type, including unknown/custom ones; disabled via `hasArchive: false`
     * in the type's `content_types` config entry.
     */
    public function hasArchive(string $type): bool
    {
        return $this->contentTypes[$type]->hasArchive ?? true;
    }

    /**
     * @return array<string, ContentTypeDefinition>
     */
    public function all(): array
    {
        return $this->contentTypes;
    }

    public function get(string $type): ?ContentTypeDefinition
    {
        return $this->contentTypes[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return $this->get($type) !== null;
    }

    /**
     * @throws InvalidArgumentException for a non-existent type name
     */
    public function require(string $type): ContentTypeDefinition
    {
        $object = $this->get($type);

        if ($object) {
            return $object;
        }

        throw new InvalidArgumentException(sprintf('Content type: %s not registered', $type));
    }

    public function register(string $type, array $params = [])
    {
        if ($this->get($type)) {
            throw new InvalidArgumentException("Content type {$type} is already registered");
        }

        if (isset($params['name'])) {
            unset($params['name']);
            trigger_error('Specifying a name different from the "type" property is not supported');
        }

        $prepared = $this->prepareArgsForType($type, $params, $this->overrides[$type] ?? []);

        NamingRules::assertTypeName($type, 'content type');
        NamingRules::assertUrlPrefix($prepared['urlPrefix'], self::describe($type));

        $this->urlPrefixes->claim($prepared['urlPrefix']);

        $def = new ContentTypeDefinition(...$prepared);
        $this->contentTypes[$type] = $def;

        return $def;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, array<string, mixed>>
     * @throws InvalidArgumentException for an override that isn't a map, or a key outside VALID_OVERRIDES
     */
    private function parseOverrides(array $overrides): array
    {
        foreach ($overrides as $type => $typeOverrides) {
            if (!is_array($typeOverrides)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid content_types override for "%s": expected a map of %s.',
                    $type,
                    implode(', ', self::VALID_OVERRIDES)
                ));
            }

            $unknown = array_diff(array_keys($typeOverrides), self::VALID_OVERRIDES);

            if ($unknown !== []) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown content_types override key(s) for "%s": %s. Allowed: %s.',
                    $type,
                    implode(', ', $unknown),
                    implode(', ', self::VALID_OVERRIDES)
                ));
            }
        }

        return $overrides;
    }

    /**
     * @param array<string, array<string, mixed>> $overrides
     */
    private function registerBuiltIn(array $overrides): void
    {
        foreach (self::BUILT_IN as $type => $params) {
            $args = $this->prepareArgsForType($type, $params, $overrides[$type] ?? []);
            NamingRules::assertUrlPrefix($args['urlPrefix'], self::describe($type));
            $this->urlPrefixes->claim($args['urlPrefix']);
            $this->contentTypes[$type] = new ContentTypeDefinition(...$args);
        }
    }

    /**
     * @param array{string, string, string} $specified [URL prefix, label, singular label]
     * @param array<string, mixed> $overrides
     * @return array<string, mixed> ContentTypeDefinition constructor arguments, by name
     */
    private function prepareArgsForType(string $type, array $specified, array $overrides = []): array
    {
        $label = $specified['label'] ?? ucwords(str_replace('_', ' ', $type));

        $defaultArgs = [
            'label'         => $label,
            'singularLabel' => $singularLabel ?? $label,
            'urlPrefix'     => $urlPrefix ?? strtolower(str_replace('_', '-', $type)),
        ];

        $prepared = array_merge($defaultArgs, $specified, $overrides ?? []);
        $prepared = Args::withoutUnknownKeys($prepared, self::VALID_OVERRIDES, 'content type');
        $prepared['name'] = $type;

        return $prepared;
    }

    /**
     * How a content type is named in error messages, e.g. `content type "seasons"`.
     */
    private static function describe(string $type): string
    {
        return sprintf('content type "%s"', $type);
    }
}
