<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Models\Media;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Renders a small inline-SVG glyph for a non-image media type, used by the
 * media library grid/picker so PDF/audio/video tiles get a recognizable icon
 * instead of a broken <img> tag (no server-side thumbnail generation).
 */
class MediaTypeIconExtension extends AbstractExtension
{
    private const ICONS = [
        Media::TYPE_PDF => '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        Media::TYPE_AUDIO => '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
        Media::TYPE_VIDEO => '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
    ];

    private const DEFAULT_ICON = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('media_type_icon', [$this, 'icon'], ['is_safe' => ['html']]),
        ];
    }

    public function icon(string $mediaType): string
    {
        return self::ICONS[$mediaType] ?? self::DEFAULT_ICON;
    }
}
