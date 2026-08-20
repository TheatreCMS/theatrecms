<?php

namespace TheatreCMS\Enums;

/**
 * Shared editorial status for content types that need a draft/published
 * workflow — currently Posts and Pages. Seasons, Productions, and Events are
 * expected to adopt this same enum (via TheatreCMS\Traits\HasContentStatus)
 * rather than each defining their own parallel status type.
 */
enum ContentStatus: string
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
