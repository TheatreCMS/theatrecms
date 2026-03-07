<?php

namespace TheatreCMS\Traits;

use Doctrine\ORM\Mapping\Column;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use TheatreCMS\Models\Production;

trait SupportsFeaturedImage
{
    private const FEATURED_IMAGE_SUBPATH = '/uploads/';
    private const RANDOM_SUFFIX_BYTES = 6;

    #[Column(name: 'featured_image_url', type: 'string', nullable: true)]
    private ?string $featuredImageUrl = null;

    public function getFeaturedImageUrl(): string
    {
        return $this->featuredImageUrl ?? '';
    }

    public function hasFeaturedImage(): bool
    {
        return $this->featuredImageUrl !== null && $this->featuredImageUrl !== '';
    }

    public function setFeaturedImageUrl(?string $featuredImageUrl): self
    {
        $this->featuredImageUrl = $featuredImageUrl;

        return $this;
    }

    private function handleFeaturedImageUpload(Request $request, Production $production): void
    {
        $uploadedFiles = $request->getUploadedFiles();
        $poster = $uploadedFiles['poster'] ?? null;

        if (!$poster instanceof UploadedFileInterface || $poster->getError() !== UPLOAD_ERR_OK) {
            return;
        }

        if (!$this->isImageUpload($poster)) {
            return;
        }

        try {
            $production->saveFeaturedImageFromUpload($poster);
        } catch (\InvalidArgumentException|\RuntimeException) {
            // Ignore invalid uploads; let other validation flow handle feedback.
        }
    }

    private function isImageUpload(UploadedFileInterface $file): bool
    {
        $mediaType = $file->getClientMediaType();
        return is_string($mediaType) && str_starts_with($mediaType, 'image/');
    }
    public function saveFeaturedImageFromUpload(UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Uploaded file is not available.');
        }

        $mediaType = $file->getClientMediaType();
        if (!is_string($mediaType) || !str_starts_with($mediaType, 'image/')) {
            throw new \InvalidArgumentException('Only image uploads are allowed for featured images.');
        }

        $directory = $this->ensureFeaturedImageDirectory();
        $filename = $this->generateFeaturedImageFilename($file);
        $targetPath = $directory . DIRECTORY_SEPARATOR . $filename;
        $file->moveTo($targetPath);

        $this->deleteFeaturedImageFile($this->featuredImageUrl);

        $this->featuredImageUrl = $this->buildFeaturedImageUrl($filename);

        return $this->featuredImageUrl;
    }

    private function ensureFeaturedImageDirectory(): string
    {
        $directory = rtrim($this->getPublicRoot(), '/\\') . self::FEATURED_IMAGE_SUBPATH;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s".', $directory));
        }

        return $directory;
    }

    private function deleteFeaturedImageFile(?string $url): void
    {
        if (!$url) {
            return;
        }

        $path = $this->resolveFeaturedImagePath($url);
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }

    private function resolveFeaturedImagePath(string $url): ?string
    {
        $relative = ltrim($url, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return $this->getPublicRoot() . '/' . $relative;
    }

    private function buildFeaturedImageUrl(string $filename): string
    {
        return rtrim(self::FEATURED_IMAGE_SUBPATH, '/') . '/' . ltrim($filename, '/');
    }

    private function generateFeaturedImageFilename(UploadedFileInterface $file): string
    {
        $extension = $this->normalizeExtension(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = 'jpg';
        }

        return sprintf(
            '%s-%s.%s',
            $this->slugForFilename($this->getSlug()),
            $this->generateRandomSuffix(),
            $extension
        );
    }

    private function slugForFilename(string $slug): string
    {
        $processed = trim(mb_strtolower($slug, 'UTF-8'));
        $processed = preg_replace('/[^a-z0-9]+/', '-', $processed);
        $processed = trim($processed, '-');

        return $processed !== '' ? $processed : 'production';
    }

    private function normalizeExtension(string $extension): string
    {
        $clean = trim(mb_strtolower($extension, 'UTF-8'));
        $clean = preg_replace('/[^a-z0-9]/', '', $clean);

        return $clean === null ? '' : $clean;
    }

    private function generateRandomSuffix(): string
    {
        return bin2hex(random_bytes(self::RANDOM_SUFFIX_BYTES));
    }

    private function getPublicRoot(): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        return rtrim($root, '/\\') . '/www';
    }
}
