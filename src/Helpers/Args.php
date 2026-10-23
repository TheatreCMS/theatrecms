<?php

namespace TheatreCMS\Helpers;

final class Args
{
    /**
     * WordPress-style argument parsing, matching wp_parse_args(): values in $args override
     * $defaults, keys missing from $args take their default, and keys only in $args are kept.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public static function parse(array $args, array $defaults): array
    {
        return array_replace($defaults, $args);
    }

    /**
     * Drops every key of $args that isn't in $allowedKeys, raising one E_USER_NOTICE per dropped
     * key, so a typo or unsupported argument is reported without stopping registration.
     *
     * @param array<string, mixed> $args
     * @param list<string> $allowedKeys
     * @param string $registration what is being registered, for the notice (e.g. "content type")
     * @return array<string, mixed> $args with only the allowed keys
     */
    public static function withoutUnknownKeys(array $args, array $allowedKeys, string $registration): array
    {
        foreach (array_keys($args) as $key) {
            if (!in_array($key, $allowedKeys, true)) {
                trigger_error(
                    sprintf('The key %s is not recognized for %s registration', $key, $registration),
                    E_USER_NOTICE
                );
                unset($args[$key]);
            }
        }

        return $args;
    }
}
