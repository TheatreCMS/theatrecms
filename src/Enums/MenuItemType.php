<?php

namespace TheatreCMS\Enums;

enum MenuItemType: string
{
    case PAGE = 'page';
    case POST = 'post';
    case PRODUCTION = 'production';
    case SEASON = 'season';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::PAGE => 'Page',
            self::POST => 'Post',
            self::PRODUCTION => 'Production',
            self::SEASON => 'Season',
            self::CUSTOM => 'Custom Link',
        };
    }

    /**
     * Returns an associative array suitable for templates.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $type) {
            $labels[$type->value] = $type->label();
        }

        return $labels;
    }
}
