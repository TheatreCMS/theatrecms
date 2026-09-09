<?php

namespace TheatreCMS\Theme;

/**
 * Tracks which content type (and, for a single item, which entity) the current frontend
 * request is rendering. Populated by `TemplateResolver::renderSingle()`/`renderList()` right
 * before the template renders, and read by the `is_single()`/`is_item()`/`is_item_id()`/
 * `is_archive()`/`is_singular()` template tags (`app/template-tags.php`) — TheatreCMS's
 * corollary to WordPress' conditional tags against `$wp_query`.
 */
class QueriedObject
{
    private static ?self $instance = null;

    private ?string $type = null;

    private ?object $entity = null;

    private bool $isSingle = false;

    private bool $isArchive = false;

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The QueriedObject has not been initialized.');
        }

        return self::$instance;
    }

    public function setSingle(string $type, object $entity): void
    {
        $this->type = $type;
        $this->entity = $entity;
        $this->isSingle = true;
        $this->isArchive = false;
    }

    public function setArchive(string $type): void
    {
        $this->type = $type;
        $this->entity = null;
        $this->isSingle = false;
        $this->isArchive = true;
    }

    public function isSingle(): bool
    {
        return $this->isSingle;
    }

    public function isArchive(?string $typeSlug = null): bool
    {
        if (!$this->isArchive) {
            return false;
        }

        return $typeSlug === null || $this->type === $typeSlug;
    }

    public function isSingular(string $typeSlug): bool
    {
        return $this->isSingle && $this->type === $typeSlug;
    }

    public function isItem(string $slug): bool
    {
        if (!$this->isSingle || $this->entity === null || !method_exists($this->entity, 'getSlug')) {
            return false;
        }

        return (string) $this->entity->getSlug() === $slug;
    }

    public function isItemId(int|string $itemId): bool
    {
        if (!$this->isSingle || $this->entity === null || !method_exists($this->entity, 'getId')) {
            return false;
        }

        return (string) $this->entity->getId() === (string) $itemId;
    }
}
