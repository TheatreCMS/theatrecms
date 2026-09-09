<?php

use TheatreCMS\Theme\QueriedObject;

if (!function_exists('is_single')) {
    /**
     * Whether the current request is rendering a single content-item page, of any type.
     */
    function is_single(): bool
    {
        return QueriedObject::getInstance()->isSingle();
    }
}

if (!function_exists('is_item')) {
    /**
     * Whether the current request is rendering the single page for the item with this slug.
     *
     * @param string $itemSlug
     */
    function is_item(string $itemSlug): bool
    {
        return QueriedObject::getInstance()->isItem($itemSlug);
    }
}

if (!function_exists('is_item_id')) {
    /**
     * Whether the current request is rendering the single page for the item with this id.
     *
     * @param int|string $itemId
     */
    function is_item_id(int|string $itemId): bool
    {
        return QueriedObject::getInstance()->isItemId($itemId);
    }
}

if (!function_exists('is_archive')) {
    /**
     * Whether the current request is rendering an archive (listing) page — for any content
     * type, or, when `$typeSlug` is given, for that specific content type.
     *
     * @param string|null $typeSlug
     */
    function is_archive(?string $typeSlug = null): bool
    {
        return QueriedObject::getInstance()->isArchive($typeSlug);
    }
}

if (!function_exists('is_singular')) {
    /**
     * Whether the current request is rendering the single page for a specific content type.
     *
     * @param string $typeSlug
     */
    function is_singular(string $typeSlug): bool
    {
        return QueriedObject::getInstance()->isSingular($typeSlug);
    }
}
