<?php

namespace TheatreCMS\ContentType;

/**
 * Content that can sit under a parent item, whose URL is then the parent's URL plus its own
 * slug (`/about/board-of-directors`, `/seasons/2005/the-glass-menagerie`).
 *
 * The parent is usually an item of the same type (pages, and entries of hierarchical types,
 * through `HasParent`); a production, whose parent is its season, is the exception.
 */
interface IsHierarchical
{
    public function hasParent(): bool;

    /**
     * The parent item, or null when hasParent() is false.
     */
    public function getParent(): ?object;
}
