<?php

namespace TheatreCMS\Services;

use Psr\Http\Message\UploadedFileInterface;
use TheatreCMS\Models\Media;
use TheatreCMS\Repositories\MediaRepository;

/**
 * Adds an uploaded file to the media library: stores it under /uploads, creates its Media
 * row and generates its image sizes. Every upload path goes through here (the media library
 * and featured-image picker, and the EditorJS image, gallery and carousel blocks), so every
 * uploaded file is listed in the library and gets the theme's image sizes.
 */
class MediaImporter
{
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
        private readonly MediaRepository $mediaRepository,
        private readonly ImageVariantGenerator $imageVariantGenerator
    ) {
    }

    /**
     * @param string $mediaType one of Media::ALL_TYPES; callers classify the file first.
     *
     * @throws \InvalidArgumentException if the file's extension or $mediaType isn't supported.
     * @throws \RuntimeException if the uploads directory can't be created.
     */
    public function import(UploadedFileInterface $file, string $mediaType): Media
    {
        $url = $this->mediaUploadService->store($file);

        try {
            $media = $this->mediaRepository->create([
                'url'              => $url,
                'filename'         => basename($url),
                'originalFilename' => $file->getClientFilename(),
                'mimeType'         => $file->getClientMediaType(),
                'sizeBytes'        => $file->getSize(),
                'mediaType'        => $mediaType,
            ]);
        } catch (\Throwable $e) {
            // Don't leave a file on disk that the library doesn't know about.
            $this->mediaUploadService->delete($url);
            throw $e;
        }

        $this->imageVariantGenerator->generate($media);

        return $media;
    }
}
