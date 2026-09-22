<?php

namespace TheatreCMS\Traits;

use TheatreCMS\Models\Media;

/**
 * Eager-loads hero image data from content_meta onto single-record fetches, mirroring
 * how a real Doctrine relation like featuredImage is always populated. Not applied to
 * fetchAll()/fetchPage() (listings) to avoid an extra content_meta query per row — hero
 * image is only ever used for single-item display.
 *
 * Using classes must extend BaseRepository (for $this->em and parent::fetch()/
 * fetchBySlug()) and inject ContentMetaRepository as $this->contentMeta.
 */
trait EagerLoadsHeroImage
{
    abstract protected function heroImageContentType(): string;

    public function fetch(int $id): ?object
    {
        return $this->attachHeroImage(parent::fetch($id));
    }

    public function fetchBySlug(string $slug): ?object
    {
        return $this->attachHeroImage(parent::fetchBySlug($slug));
    }

    private function attachHeroImage(?object $entity): ?object
    {
        if ($entity === null) {
            return null;
        }

        $mediaId = $this->contentMeta->get($this->heroImageContentType(), $entity->getId(), 'hero_image_id');

        $entity->setHasHeroImage(!empty($mediaId));

        $image = match (empty($mediaId)) {
            true    => method_exists($entity, 'getFeaturedImage') ? $entity->getFeaturedImage() : null,
            default => $this->em->getRepository(Media::class)->find((int) $mediaId),
        };

        $entity->setHeroImageUrl($image instanceof Media ? $image->getUrl() : null);

        return $entity;
    }
}
