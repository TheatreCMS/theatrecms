<?php

namespace TheatreCMS\Services;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\ImageManager;
use TheatreCMS\Models\Media;
use TheatreCMS\Models\MediaVariant;
use TheatreCMS\Theme\ImageSizeRegistry;

/**
 * Generates and removes the registered thumbnail sizes (ImageSizeRegistry) for
 * a Media image, persisting one MediaVariant row per generated size.
 */
class ImageVariantGenerator
{
    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly MediaUploadService $mediaUploadService,
        private readonly EntityManagerInterface $em,
        private readonly ImageSizeRegistry $imageSizeRegistry
    ) {
    }

    /**
     * @param array<int, string>|null $onlySizes limit generation to these
     *        registered size names; null generates every registered size.
     */
    public function generate(Media $media, ?array $onlySizes = null): void
    {
        if ($media->getMediaType() !== Media::TYPE_IMAGE) {
            return;
        }

        $sourcePath = $this->mediaUploadService->resolvePath($media->getUrl());
        if ($sourcePath === null || !is_file($sourcePath)) {
            return;
        }

        $sizes = $this->imageSizeRegistry->all();
        $directory = dirname($sourcePath);
        $urlDirectory = dirname($media->getUrl());
        $basename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);

        foreach ($sizes as $name => $size) {
            if ($onlySizes !== null && !in_array($name, $onlySizes, true)) {
                continue;
            }

            $image = $this->imageManager->read($sourcePath);

            if ($size->crop) {
                $image->cover($size->width, $size->height);
            } else {
                $image->scaleDown($size->width, $size->height);
            }

            $variantFilename = sprintf('%s-%s.%s', $basename, $name, $extension);
            $image->save($directory . DIRECTORY_SEPARATOR . $variantFilename);
            $variantUrl = rtrim($urlDirectory, '/') . '/' . $variantFilename;

            $variant = $this->em->getRepository(MediaVariant::class)->findOneBy([
                'media' => $media,
                'sizeName' => $name,
            ]);

            if ($variant === null) {
                $variant = new MediaVariant($media, $name, $variantUrl, $image->width(), $image->height());
                $this->em->persist($variant);
                $media->addVariant($variant);
            } else {
                $variant->setUrl($variantUrl)->setWidth($image->width())->setHeight($image->height());
            }
        }

        $this->em->flush();
    }

    /**
     * Removes the physical files for every generated variant of this media.
     * The MediaVariant rows themselves are removed via the entity cascade /
     * ON DELETE CASCADE when the Media row is deleted.
     */
    public function deleteFiles(Media $media): void
    {
        foreach ($media->getVariants() as $variant) {
            $this->mediaUploadService->delete($variant->getUrl());
        }
    }
}
