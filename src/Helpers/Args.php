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
}
