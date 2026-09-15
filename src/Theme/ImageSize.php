<?php

namespace TheatreCMS\Theme;

/**
 * A registered thumbnail size: a name plus the dimensions and crop behavior
 * `ImageVariantGenerator` uses to produce it.
 *
 * `crop = true` hard-crops to exactly width x height (fills the box).
 * `crop = false` scales proportionally to fit within width x height without
 * upscaling.
 */
final class ImageSize
{
    public function __construct(
        public readonly string $name,
        public readonly int $width,
        public readonly int $height,
        public readonly bool $crop = false
    ) {
    }
}
