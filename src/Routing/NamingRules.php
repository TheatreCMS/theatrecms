<?php

namespace TheatreCMS\Routing;

use InvalidArgumentException;

/**
 * The naming rules shared by content types and taxonomies: the format of their machine
 * names and of the URL prefixes their frontend routes are served under.
 *
 * Both registries validate through here so the rules can't drift apart. Whether a prefix is
 * already taken is tracked separately, across both registries, by `UrlPrefixRegistry`.
 */
final class NamingRules
{
    /**
     * URL prefixes no content type or taxonomy may use, because core routes own them.
     *
     * @var list<string>
     */
    public const RESERVED_URL_PREFIXES = ['admin'];

    /**
     * A machine name (e.g. `press_release`) is lowercase letters and underscores.
     *
     * @param string $kind what is being named, for the error message (e.g. "content type")
     * @throws InvalidArgumentException
     */
    public static function assertTypeName(string $name, string $kind): void
    {
        if (!preg_match('/^[a-z_]+$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s name "%s": use lowercase letters and underscores.',
                $kind,
                $name
            ));
        }
    }

    /**
     * A URL prefix (e.g. `press-releases`) is a single path segment of lowercase letters and
     * hyphens, and can't be one of RESERVED_URL_PREFIXES.
     *
     * @param string $subject what the prefix is for, in the error message (e.g. `content type "seasons"`)
     * @throws InvalidArgumentException
     */
    public static function assertUrlPrefix(string $urlPrefix, string $subject): void
    {
        if (!preg_match('/^[a-z-]+$/', $urlPrefix)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid URL prefix "%s" for %s: use lowercase letters and hyphens.',
                $urlPrefix,
                $subject
            ));
        }

        if (in_array($urlPrefix, self::RESERVED_URL_PREFIXES, true)) {
            throw new InvalidArgumentException(sprintf(
                'URL prefix "%s" for %s is reserved for core routes.',
                $urlPrefix,
                $subject
            ));
        }
    }
}
