<?php

namespace Clubdeuce\TheatreCMS\Helpers;

class Sanitize
{
    public static function withDashes(string $string): string
    {
        $string = str_replace('-', ' ', $string);

        return $string;
    }
}
