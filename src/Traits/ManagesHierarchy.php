<?php

namespace TheatreCMS\Traits;

use InvalidArgumentException;

/**
 * Repository support for a hierarchical content type, whose model uses `HasParent`: finding a
 * public item by its path, keeping slugs unique among siblings, and keeping children when their
 * parent is deleted. For a `BaseRepository` subclass.
 */
trait ManagesHierarchy
{
    /**
     * The publicly visible item at `$path` (`about/board-of-directors`): each segment must be a
     * public child of the one before it, starting from a top-level item. Null otherwise, so an
     * unpublished ancestor hides its descendants.
     */
    public function fetchPublicByPath(string $path): ?object
    {
        $item = null;

        foreach (explode('/', trim($path, '/')) as $slug) {
            $builder = $this->createListQueryBuilder()
                ->andWhere('e.slug = :slug')
                ->setParameter('slug', $slug)
                ->setMaxResults(1);

            if ($item === null) {
                $builder->andWhere('e.parent IS NULL');
            } else {
                $builder->andWhere('e.parent = :parent')->setParameter('parent', $item);
            }

            $this->applyPublicFilter($builder, 'e');
            $item = $builder->getQuery()->getOneOrNullResult();

            if ($item === null) {
                return null;
            }
        }

        return $item;
    }

    /**
     * Moves `$item` under `$parent` (null for top level), renaming its slug if a new sibling
     * already uses it.
     *
     * @throws InvalidArgumentException when `$parent` is the item or one of its descendants
     */
    public function moveTo(object $item, ?object $parent): void
    {
        if ($item->getParent() === $parent) {
            return;
        }

        $item->setParent($parent);
        $item->setSlug($this->generateUniqueSlug($item->getSlug(), ['parent' => $parent]));
    }

    /**
     * Every item except `$item` and its descendants: the parents `$item` may be moved under.
     *
     * @return array<int, object>
     */
    public function fetchPossibleParents(?object $item): array
    {
        return array_values(array_filter(
            $this->createListQueryBuilder()->getQuery()->getResult(),
            static function (object $candidate) use ($item): bool {
                for ($ancestor = $candidate; $ancestor !== null; $ancestor = $ancestor->getParent()) {
                    if ($ancestor === $item) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * Hands `$item`'s children to its own parent, so deleting it doesn't orphan or delete them.
     */
    protected function reparentChildren(object $item): void
    {
        $children = $this->createListQueryBuilder()
            ->andWhere('e.parent = :parent')
            ->setParameter('parent', $item)
            ->getQuery()
            ->getResult();

        $reservedSlugs = [];

        foreach ($children as $child) {
            $this->moveTo($child, $item->getParent());
            $slug = $child->getSlug();
            $suffix = 0;

            while (isset($reservedSlugs[$slug])) {
                $slug = $this->generateUniqueSlug(
                    $child->getSlug() . '-' . ++$suffix,
                    ['parent' => $item->getParent()]
                );
            }

            $child->setSlug($slug);
            $reservedSlugs[$slug] = true;
        }
    }
}
