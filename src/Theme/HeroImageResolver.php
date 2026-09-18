<?php

namespace TheatreCMS\Theme;

use Doctrine\ORM\EntityManagerInterface;
use TheatreCMS\Models\Media;
use TheatreCMS\Models\Production;
use TheatreCMS\Repositories\ContentMetaRepository;

/**
 * Resolves a Production's hero image URL from content_meta and attaches it to the
 * entity's transient `heroImageUrl` property, since Production has no Doctrine
 * relation for it (unlike featuredImage) and can't look this up itself.
 */
class HeroImageResolver
{
    private const CONTENT_TYPE = 'production';

    private const META_KEY = 'hero_image_id';

    public function __construct(
        private readonly ContentMetaRepository $contentMeta,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function resolve(Production $production): ?string
    {
        $mediaId = $this->contentMeta->get(self::CONTENT_TYPE, $production->getId(), self::META_KEY);

        if (empty($mediaId)) {
            return null;
        }

        $image = $this->entityManager->getRepository(Media::class)->find((int) $mediaId);

        return $image instanceof Media ? $image->getUrl() : null;
    }

    public function attach(Production $production): Production
    {
        return $production->setHeroImageUrl($this->resolve($production));
    }
}
