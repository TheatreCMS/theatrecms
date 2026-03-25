<?php

namespace TheatreCMS\Enums;

enum PostStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
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
        foreach (self::cases() as $status) {
            $labels[$status->value] = $status->label();
        }

        return $labels;
    }
}
