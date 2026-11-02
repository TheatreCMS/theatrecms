<?php

namespace TheatreCMS\Traits;

use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use InvalidArgumentException;

/**
 * A mapped `parent_id` pointing at another item of the same class, implementing
 * `IsHierarchical` for content types registered with `hierarchical: true`.
 *
 * Repositories keep the tree valid (see `ManagesHierarchy`); setParent() itself only refuses
 * a parent that would create a cycle.
 */
trait HasParent
{
    #[ManyToOne(targetEntity: self::class)]
    #[JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    public function hasParent(): bool
    {
        return $this->parent !== null;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @throws InvalidArgumentException when `$parent` is this item or one of its descendants
     */
    public function setParent(?self $parent): self
    {
        for ($ancestor = $parent; $ancestor !== null; $ancestor = $ancestor->getParent()) {
            if ($ancestor === $this) {
                throw new InvalidArgumentException('An item cannot be its own parent or ancestor.');
            }
        }

        $this->parent = $parent;

        return $this;
    }
}
