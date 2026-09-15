<?php

use TheatreCMS\Theme\ImageSizeRegistry;

if (!function_exists('register_image_size')) {
    /**
     * Declares a named thumbnail size to generate for every uploaded image
     * (e.g. 'hero', 800, 450, true), retrievable later via
     * `Media::getVariantUrl($name)` or `the_featured_image_url($entity, $name)`.
     *
     * @param string $name
     * @param int $width
     * @param int $height
     * @param bool $crop hard-crop to exactly width x height when true;
     *        scale proportionally to fit within the box (no upscaling) when false.
     */
    function register_image_size(string $name, int $width, int $height, bool $crop = false): void
    {
        ImageSizeRegistry::getInstance()->register($name, $width, $height, $crop);
    }
}
