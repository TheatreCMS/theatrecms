<?php

namespace TheatreCMS\Services;

use TheatreCMS\Models\Media;

/**
 * Classifies an uploaded file into one of Media::ALL_TYPES from its (client-
 * supplied) mime type and/or filename extension.
 *
 * Mime matching uses a prefix for image/audio/video, since real-world
 * browsers/OSes report inconsistent exact mime strings for these (e.g.
 * `video/quicktime` for `.mov`, `audio/mpeg` for `.mp3`). PDF has no useful
 * prefix family, so it's matched exactly. The extension allowlist is the
 * fallback signal, used when the mime type is missing, generic (e.g.
 * `application/octet-stream`), or doesn't match a known prefix/exact value.
 */
final class MediaTypeClassifier
{
    private const EXTENSIONS_BY_TYPE = [
        Media::TYPE_IMAGE => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        Media::TYPE_PDF   => ['pdf'],
        Media::TYPE_AUDIO => ['mp3', 'wav', 'ogg', 'm4a'],
        Media::TYPE_VIDEO => ['mp4', 'webm', 'mov'],
    ];

    private const MIME_PREFIXES_BY_TYPE = [
        Media::TYPE_IMAGE => ['image/'],
        Media::TYPE_AUDIO => ['audio/'],
        Media::TYPE_VIDEO => ['video/'],
    ];

    private const MIME_EXACT_BY_TYPE = [
        Media::TYPE_PDF => ['application/pdf'],
    ];

    /**
     * @return string One of Media::ALL_TYPES; Media::TYPE_OTHER when neither
     *                 the mime type nor the extension matches a known category.
     */
    public static function classify(?string $mimeType, string $extension): string
    {
        $mimeType = $mimeType !== null ? strtolower(trim($mimeType)) : null;
        $extension = strtolower(ltrim($extension, '.'));

        if ($mimeType !== null) {
            foreach (self::MIME_EXACT_BY_TYPE as $type => $exactMimes) {
                if (in_array($mimeType, $exactMimes, true)) {
                    return $type;
                }
            }

            foreach (self::MIME_PREFIXES_BY_TYPE as $type => $prefixes) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($mimeType, $prefix)) {
                        return $type;
                    }
                }
            }
        }

        foreach (self::EXTENSIONS_BY_TYPE as $type => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $type;
            }
        }

        return Media::TYPE_OTHER;
    }

    public static function isSupported(?string $mimeType, string $extension): bool
    {
        return self::classify($mimeType, $extension) !== Media::TYPE_OTHER;
    }

    /**
     * @return string[] Flattened, deduplicated list of extensions across every
     *                   supported (non-OTHER) category.
     */
    public static function allowedExtensions(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::EXTENSIONS_BY_TYPE))));
    }
}
