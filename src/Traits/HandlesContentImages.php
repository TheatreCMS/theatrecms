<?php

namespace TheatreCMS\Traits;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use TheatreCMS\Models\Media;

/**
 * Shared featured-image + hero-image write path (apply on store/update, remove action)
 * for any BaseController subclass whose entity uses the HasFeaturedImage and
 * HasHeroImage model traits.
 *
 * Using classes must inject ContentMetaRepository as $this->contentMeta and implement
 * contentType() (used as the content_meta key and the shared image-field partial's
 * entityType param). routePrefix() defaults to contentType() . 's' — override it when
 * that's wrong (e.g. Person -> 'people').
 */
trait HandlesContentImages
{
    private const HERO_IMAGE_META_KEY = 'hero_image_id';

    abstract protected function contentType(): string;

    protected function routePrefix(): string
    {
        return $this->contentType() . 's';
    }

    protected function applyFeaturedImage(object $entity, mixed $featuredImageId): void
    {
        if (empty($featuredImageId)) {
            $entity->setFeaturedImage(null);
            return;
        }

        $image = $this->entityManager->getRepository(Media::class)->find((int) $featuredImageId);

        if ($image instanceof Media && !$image->isImage()) {
            throw new \InvalidArgumentException('Featured media must be an image.');
        }

        $entity->setFeaturedImage($image);
    }

    protected function applyHeroImage(int $entityId, mixed $heroImageId): void
    {
        if (empty($heroImageId)) {
            $this->contentMeta->delete($this->contentType(), $entityId, self::HERO_IMAGE_META_KEY);
            return;
        }

        $image = $this->entityManager->getRepository(Media::class)->find((int) $heroImageId);

        if (!$image instanceof Media) {
            $this->contentMeta->delete($this->contentType(), $entityId, self::HERO_IMAGE_META_KEY);
            return;
        }

        if (!$image->isImage()) {
            throw new \InvalidArgumentException('Hero media must be an image.');
        }

        $this->contentMeta->set($this->contentType(), $entityId, self::HERO_IMAGE_META_KEY, (string) $image->getId());
    }

    public function removeFeaturedImage(Request $request, Response $response, array $args = []): Response
    {
        $entity = $this->repository->fetch((int) ($args['id'] ?? 0));

        if ($entity === null) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => ucfirst($this->contentType()) . ' not found.',
                ]);
            }

            return $response->withStatus(404);
        }

        $entity->setFeaturedImage(null);
        $this->repository->update($entity);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_featured_image_field.html.twig', [
                'entityType'       => $this->contentType(),
                'entityId'         => $entity->getId(),
                'featuredImageUrl' => null,
                'featuredImageId'  => null,
            ]);
        }

        return $response->withHeader('Location', '/admin/' . $this->routePrefix() . '/edit/' . $entity->getId());
    }

    public function removeHeroImage(Request $request, Response $response, array $args = []): Response
    {
        $entity = $this->repository->fetch((int) ($args['id'] ?? 0));

        if ($entity === null) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => ucfirst($this->contentType()) . ' not found.',
                ]);
            }

            return $response->withStatus(404);
        }

        $this->contentMeta->delete($this->contentType(), $entity->getId(), self::HERO_IMAGE_META_KEY);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_featured_image_field.html.twig', [
                'entityType'       => $this->contentType(),
                'entityId'         => $entity->getId(),
                'field'            => 'hero',
                'label'            => 'Hero Image',
                'deleteUrl'        => '/admin/' . $this->routePrefix() . '/' . $entity->getId() . '/hero-image',
                'featuredImageUrl' => null,
                'featuredImageId'  => null,
            ]);
        }

        return $response->withHeader('Location', '/admin/' . $this->routePrefix() . '/edit/' . $entity->getId());
    }
}
