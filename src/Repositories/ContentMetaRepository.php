<?php

namespace TheatreCMS\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use TheatreCMS\Models\ContentMeta;

/**
 * Generic key/value metadata store for any content type, keyed by
 * (content_type, content_id, meta_key). Deliberately does not extend
 * BaseRepository/PaginatedRepositoryInterface — that base is shaped for
 * top-level, sluggable, paginated list resources, which meta rows are not.
 */
class ContentMetaRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function get(string $contentType, int $contentId, string $key): ?string
    {
        $meta = $this->findOne($contentType, $contentId, $key);

        return $meta?->getMetaValue();
    }

    /**
     * @return array<string, string>
     */
    public function getAllForContent(string $contentType, int $contentId): array
    {
        /** @var ContentMeta[] $rows */
        $rows = $this->em->getRepository(ContentMeta::class)->findBy([
            'contentType' => $contentType,
            'contentId' => $contentId,
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[$row->getMetaKey()] = $row->getMetaValue() ?? '';
        }

        return $result;
    }

    public function set(string $contentType, int $contentId, string $key, string $value): ContentMeta
    {
        $meta = $this->findOne($contentType, $contentId, $key);

        if ($meta === null) {
            $meta = new ContentMeta($contentType, $contentId, $key, $value);
            $this->em->persist($meta);
        } else {
            $meta->setMetaValue($value);
        }

        $this->em->flush();

        return $meta;
    }

    public function delete(string $contentType, int $contentId, string $key): void
    {
        $meta = $this->findOne($contentType, $contentId, $key);

        if ($meta === null) {
            return;
        }

        $this->em->remove($meta);
        $this->em->flush();
    }

    public function deleteAllForContent(string $contentType, int $contentId): void
    {
        /** @var ContentMeta[] $rows */
        $rows = $this->em->getRepository(ContentMeta::class)->findBy([
            'contentType' => $contentType,
            'contentId' => $contentId,
        ]);

        foreach ($rows as $row) {
            $this->em->remove($row);
        }

        $this->em->flush();
    }

    private function findOne(string $contentType, int $contentId, string $key): ?ContentMeta
    {
        return $this->em->getRepository(ContentMeta::class)->findOneBy([
            'contentType' => $contentType,
            'contentId' => $contentId,
            'metaKey' => $key,
        ]);
    }
}
