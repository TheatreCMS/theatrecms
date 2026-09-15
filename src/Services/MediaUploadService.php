<?php

namespace TheatreCMS\Services;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Stores and removes admin-uploaded media files under the public /uploads
 * directory.
 *
 * Shared by any controller that lets an editor attach a file to a record
 * (sponsor logos, post/production featured images, media library uploads,
 * etc.) so the storage, filename generation, and path-traversal guarding
 * only live in one place.
 */
class MediaUploadService
{
    private const UPLOADS_SUBPATH = '/uploads/';
    private const MAX_SLUG_LENGTH = 80;

    public function __construct(private readonly string $publicRoot)
    {
    }

    /**
     * Moves an uploaded file into the uploads directory and returns its public URL.
     * The stored filename is a slug of the original name (e.g. "poster.jpg"),
     * not the original bytes — collisions get a "-1", "-2", ... suffix.
     *
     * @throws \InvalidArgumentException if the file's extension isn't one of
     *         MediaTypeClassifier::allowedExtensions() — callers are expected
     *         to classify/reject unsupported files before calling store().
     * @throws \RuntimeException if the uploads directory can't be created.
     */
    public function store(UploadedFileInterface $file): string
    {
        $directory = $this->ensureUploadsDirectory();
        $original = $file->getClientFilename() ?? '';
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($extension, MediaTypeClassifier::allowedExtensions(), true)) {
            throw new \InvalidArgumentException('Unsupported file extension.');
        }

        $filename = $this->generateUniqueFilename(pathinfo($original, PATHINFO_FILENAME), $extension, $directory);
        $file->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return rtrim(self::UPLOADS_SUBPATH, '/') . '/' . $filename;
    }

    /**
     * Computes a filesystem-unique filename for the given desired base name
     * and extension within $directory, appending "-1", "-2", ... on collision.
     * Read-only — does not create or touch any file, so it's safe to call for
     * a dry-run preview.
     *
     * $excludeFilename, when given, is treated as not a collision — pass the
     * file's own current name when renaming it in place, so re-checking a
     * file already at its target name doesn't see itself as taken and get
     * needlessly suffixed.
     */
    public function generateUniqueFilename(
        string $desiredBase,
        string $extension,
        string $directory,
        ?string $excludeFilename = null
    ): string {
        $base = $this->slugify($desiredBase);
        if ($base === '') {
            $base = 'file';
        }

        $filename = $base . '.' . $extension;
        $suffix = 1;
        while ($filename !== $excludeFilename && file_exists($directory . DIRECTORY_SEPARATOR . $filename)) {
            $filename = $base . '-' . $suffix . '.' . $extension;
            $suffix++;
        }

        return $filename;
    }

    /**
     * Renames an existing stored upload to $newFilename in the same directory.
     * Returns the new public URL, or null if the source file doesn't exist.
     */
    public function renameTo(string $url, string $newFilename): ?string
    {
        $path = $this->resolvePath($url);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $newPath = dirname($path) . DIRECTORY_SEPARATOR . $newFilename;
        if ($newPath !== $path) {
            rename($path, $newPath);
        }

        return dirname($url) . '/' . $newFilename;
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

    /**
     * Maps a stored `/uploads/...` public URL back to its filesystem path.
     * Returns null for empty/foreign/traversal-attempting URLs.
     */
    public function resolvePath(string $url): ?string
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

    /**
     * Best-effort transliterates accented Latin characters to ASCII (e.g.
     * "café" -> "cafe"), then lowercases and hyphenates anything that isn't
     * a-z0-9, capping the result well under the media.filename column budget.
     */
    private function slugify(string $string): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        $string = strtolower($ascii !== false ? $ascii : $string);
        $string = preg_replace('/[^a-z0-9]+/', '-', $string) ?? '';

        return mb_substr(trim($string, '-'), 0, self::MAX_SLUG_LENGTH);
    }
}
