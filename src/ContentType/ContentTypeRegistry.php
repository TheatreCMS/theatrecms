<?php

namespace TheatreCMS\ContentType;

use InvalidArgumentException;
use RuntimeException;
use TheatreCMS\Controllers\EntryController;
use TheatreCMS\Helpers\Args;
use TheatreCMS\Routing\NamingRules;
use TheatreCMS\Routing\UrlPrefixRegistry;

/**
 * Holds a definition (see `ContentTypeDefinition`) for every registered content type: its URL
 * path segment, display labels and whether it has an archive page.
 *
 * The registry starts empty and only provides the mechanism. Core's own types are registered
 * at bootstrap from `app/core-content-types.php`; plugins and themes call
 * `register_content_type()` (`app/content-types.php`).
 *
 * A site can override any registered type's prefix, labels or archive flag via the
 * `content_types` key in `app/config.yaml`; the override is applied when the type registers,
 * whoever registers it. For example, a
 * theatre that calls its season listing "Shows" can serve it under `/shows` with "Shows"
 * as the label everywhere (URLs, menu links, permalinks and page `<title>`s), without any
 * code or template changes:
 *
 *     content_types:
 *         season:
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
     * @var array<string, ContentTypeDefinition>
     */
    private array $contentTypes = [];

    /**
     * @var array<string, array<string, mixed>> content type => its `content_types` config override
     */
    private readonly array $overrides;

    private readonly UrlPrefixRegistry $urlPrefixes;

    private static ?ContentTypeRegistry $instance = null;

    /**
     * @param array<string, mixed> $overrides content type => override map from config, e.g.
     *        `['season' => ['urlPrefix' => 'shows', 'label' => 'Shows', 'hasArchive' => false]]`.
     *        Allowed keys are listed in VALID_OVERRIDES.
     * @param UrlPrefixRegistry|null $urlPrefixes prefix claims shared with `TaxonomyRegistry`, so
     *        a content type and a taxonomy can't use the same prefix; a private one if omitted
     * @throws InvalidArgumentException for an override that isn't a map, or an unknown key
     */
    public function __construct(
        array $overrides = [],
        ?UrlPrefixRegistry $urlPrefixes = null,
    ) {
        $this->urlPrefixes = $urlPrefixes ?? new UrlPrefixRegistry();

        $this->overrides = $this->parseOverrides($overrides);
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
     *
     * @throws InvalidArgumentException for an unregistered type
     */
    public function prefix(string $type): string
    {
        return $this->require($type)->urlPrefix;
    }

    /**
     * Returns the display label (e.g. for page titles and menu links) for a content type.
     *
     * @throws InvalidArgumentException for an unregistered type
     */
    public function label(string $type): string
    {
        return $this->require($type)->label;
    }

    /**
     * Whether a content type's archive (listing) page should be served; turned off by registering
     * it with, or overriding it to, `hasArchive: false`.
     *
     * @throws InvalidArgumentException for an unregistered type
     */
    public function hasArchive(string $type): bool
    {
        return $this->require($type)->hasArchive;
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

    /**
     * Registers a content type, applying its `content_types` config override (which wins over
     * `$params`) and claiming its URL prefix. Unknown keys in `$params` raise one E_USER_NOTICE
     * each and are ignored.
     *
     * @param array<string, mixed> $params ContentTypeDefinition constructor arguments other than `name`
     * @throws InvalidArgumentException for an invalid or already registered name, or an invalid or
     *        already claimed URL prefix
     */
    public function register(string $type, array $params = []): ContentTypeDefinition
    {
        if (isset($params['controller'])) {
            $controller = $params['controller'];
            unset($params['controller']);
        }

        $this->assertRegistrationArgs($type, $params);

        $prepared = $this->prepareArgsForType($type, $params, $this->overrides[$type] ?? []);
        $prepared['controller'] = $controller ?? EntryController::class;

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
     * Merges the defaults derived from the type's name, the args it is registered with, and its
     * config override, each winning over the one before, keeping only keys in VALID_OVERRIDES.
     *
     * @param array<string, mixed> $specified the args the type is registered with
     * @param array<string, mixed> $overrides the type's `content_types` config override
     * @return array<string, mixed> ContentTypeDefinition constructor arguments, by name
     */
    private function prepareArgsForType(string $type, array $specified, array $overrides = []): array
    {
        unset($specified['name']);

        $singularLabel = $specified['singularLabel'] ?? ucwords(str_replace('_', ' ', $type));

        $defaultArgs = [
            'label'         => "{$singularLabel}s",
            'singularLabel' => $singularLabel,
            'urlPrefix'     => strtolower(str_replace('_', '-', $type)),
        ];

        $prepared = array_merge($defaultArgs, $specified, $overrides);
        $prepared = Args::withoutUnknownKeys($prepared, self::VALID_OVERRIDES, 'content type');
        $prepared['name'] = $type;

        return $prepared;
    }

    /**
     * How a content type is named in error messages, e.g. `content type "season"`.
     */
    private static function describe(string $type): string
    {
        return sprintf('content type "%s"', $type);
    }

    /**
     * Verifies that the registration arguments for a content type are valid.
     *
     * @throws InvalidArgumentException if the arguments are invalid.
     */
    private function assertRegistrationArgs(string $type, array $args): void
    {
        if ($this->get($type)) {
            throw new InvalidArgumentException("Content type {$type} is already registered");
        }

        if (isset($args['name'])) {
            trigger_error('Specifying a name different from the "type" property is not supported');
        }
    }
}
