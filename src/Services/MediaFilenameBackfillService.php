<?php

namespace TheatreCMS\Services;

use Doctrine\ORM\EntityManagerInterface;
use TheatreCMS\Models\Media;

/**
 * One-time backfill: renames existing media library files from their
 * original hash-based name to a slugified version of their original upload
 * filename (see MediaUploadService::generateUniqueFilename()), for
 * SEO-friendly URLs. Regenerates registered thumbnail sizes under the new
 * name for images.
 *
 * Skips any row with no known original filename (e.g. registered by
 * ./backfill-images from a pre-existing file on disk, which never had a
 * client-supplied name) since there's no better name available for it.
 *
 * Safe to run more than once: rows whose current filename already matches
 * the computed target are left untouched.
 */
class MediaFilenameBackfillService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MediaUploadService $mediaUploadService,
        private readonly ImageVariantGenerator $imageVariantGenerator
    ) {
    }

    /**
     * @return array<int, array{id: int, from: string, to: string}> renamed
     *         (or, in dry-run, would-be-renamed) rows
     */
    public function renameToSeoSlugs(bool $dryRun = false): array
    {
        $changes = [];

        /** @var Media[] $allMedia */
        $allMedia = $this->em->getRepository(Media::class)->findAll();

        foreach ($allMedia as $media) {
            $original = $media->getOriginalFilename();
            if ($original === null || trim($original) === '') {
                continue;
            }

            $oldUrl = $media->getUrl();
            $path = $this->mediaUploadService->resolvePath($oldUrl);
            if ($path === null || !is_file($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $desiredBase = pathinfo($original, PATHINFO_FILENAME);
            $currentFilename = basename($path);
            $newFilename = $this->mediaUploadService->generateUniqueFilename(
                $desiredBase,
                $extension,
                dirname($path),
                $currentFilename
            );

            if ($newFilename === $currentFilename) {
                continue;
            }

            $newUrlPreview = dirname($oldUrl) . '/' . $newFilename;

            if ($dryRun) {
                $changes[] = ['id' => $media->getId(), 'from' => $oldUrl, 'to' => $newUrlPreview];
                continue;
            }

            $isImage = $media->getMediaType() === Media::TYPE_IMAGE;
            $newUrl = $this->mediaUploadService->renameTo($oldUrl, $newFilename);
            if ($newUrl === null) {
                continue;
            }

            if ($isImage) {
                $this->imageVariantGenerator->deleteFiles($media);
            }

            $media->setUrl($newUrl)->setFilename($newFilename);
            $this->em->persist($media);
            $this->em->flush();

            if ($isImage) {
                $this->imageVariantGenerator->generate($media);
            }

            $changes[] = ['id' => $media->getId(), 'from' => $oldUrl, 'to' => $newUrl];
        }

        return $changes;
    }
}
