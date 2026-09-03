<?php

namespace TheatreCMS\Repositories;

use Doctrine\ORM\QueryBuilder;
use TheatreCMS\Models\Image;

class ImageRepository extends BaseRepository
{
    protected string $entityClass = Image::class;

    /**
     * @param array<string, mixed> $args
     */
    public function create(array $args): Image
    {
        $args = array_merge([
            'url' => null,
            'filename' => null,
            'originalFilename' => null,
            'mimeType' => null,
            'sizeBytes' => null,
            'altText' => null,
        ], $args);

        if (empty($args['url'])) {
            throw new \InvalidArgumentException('Image URL is required.');
        }

        $filename = $args['filename'] ?: basename((string) $args['url']);

        $image = new Image($args['url'], $filename);
        $image->setOriginalFilename($args['originalFilename'])
            ->setMimeType($args['mimeType'])
            ->setSizeBytes($args['sizeBytes'] !== null ? (int) $args['sizeBytes'] : null)
            ->setAltText($args['altText']);

        $dimensions = $this->resolveDimensions($args['url']);
        if ($dimensions !== null) {
            $image->setWidth($dimensions[0])->setHeight($dimensions[1]);
        }

        $this->em->persist($image);
        $this->em->flush();

        return $image;
    }

    public function findByUrl(string $url): ?Image
    {
        return $this->em->getRepository(Image::class)->findOneBy(['url' => $url]);
    }

    protected function applySearchFilter(QueryBuilder $builder, string $alias, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $builder->andWhere(sprintf(
            '%1$s.filename LIKE :search OR %1$s.originalFilename LIKE :search OR %1$s.altText LIKE :search',
            $alias
        ))->setParameter('search', '%' . $search . '%');
    }

    protected function applyRequestedSort(QueryBuilder $builder, string $alias, string $sort, string $direction): bool
    {
        $columns = ['filename' => 'filename', 'uploadedAt' => 'uploadedAt', 'sizeBytes' => 'sizeBytes'];

        if (!isset($columns[$sort])) {
            return false;
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $builder->orderBy(sprintf('%s.%s', $alias, $columns[$sort]), $direction)
            ->addOrderBy(sprintf('%s.id', $alias), 'DESC');

        return true;
    }

    protected function applyListOrder(QueryBuilder $builder, string $alias): void
    {
        $builder->orderBy(sprintf('%s.uploadedAt', $alias), 'DESC')
            ->addOrderBy(sprintf('%s.id', $alias), 'DESC');
    }

    /**
     * @return array{0: int, 1: int}|null [width, height]
     */
    private function resolveDimensions(string $url): ?array
    {
        if (!str_starts_with($url, '/uploads/') || str_contains($url, '..')) {
            return null;
        }

        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $path = rtrim($root, '/\\') . '/www' . $url;

        if (!is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);

        return $size !== false ? [$size[0], $size[1]] : null;
    }
}
