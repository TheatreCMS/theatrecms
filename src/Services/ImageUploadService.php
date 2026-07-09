<?php

namespace TheatreCMS\Services;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Stores and removes admin-uploaded images under the public /uploads directory.
 *
 * Shared by any controller that lets an editor attach an image to a record
 * (sponsor logos, post/production featured images, etc.) so the storage,
 * filename generation, and path-traversal guarding only live in one place.
 */
class ImageUploadService
{
    private const UPLOADS_SUBPATH = '/uploads/';
    private const RANDOM_SUFFIX_BYTES = 12;
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(private readonly string $publicRoot)
    {
    }

    public function isImage(UploadedFileInterface $file): bool
    {
        $mediaType = $file->getClientMediaType();

        return is_string($mediaType) && str_starts_with($mediaType, 'image/');
    }

    /**
     * Moves an uploaded file into the uploads directory and returns its public URL.
     */
    public function store(UploadedFileInterface $file): string
    {
        $directory = $this->ensureUploadsDirectory();
        $filename = $this->generateFilename($file);
        $file->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return rtrim(self::UPLOADS_SUBPATH, '/') . '/' . $filename;
    }

    /**
     * Deletes a previously stored upload from disk, given its public URL.
     * Silently no-ops for empty/foreign/traversal-attempting URLs.
     */
    public function delete(?string $url): void
    {
        if (!$url) {
            return;
        }

        $path = $this->resolvePath($url);
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }

    private function resolvePath(string $url): ?string
    {
        if (!str_starts_with($url, self::UPLOADS_SUBPATH)) {
            return null;
        }

        $relative = ltrim($url, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return $this->publicRoot . '/' . $relative;
    }

    private function ensureUploadsDirectory(): string
    {
        $directory = $this->publicRoot . self::UPLOADS_SUBPATH;

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create upload directory "%s".', $directory));
        }

        return rtrim($directory, '/\\');
    }

    private function generateFilename(UploadedFileInterface $file): string
    {
        $original = $file->getClientFilename() ?? '';
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $extension = 'jpg';
        }

        return sprintf('%s.%s', bin2hex(random_bytes(self::RANDOM_SUFFIX_BYTES)), $extension);
    }
}
