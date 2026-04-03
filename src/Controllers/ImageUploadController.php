<?php

namespace TheatreCMS\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Handles EditorJS-compatible image uploads for the Image Gallery block.
 *
 * POST /admin/images/upload
 *   Content-Type: multipart/form-data
 *   Field:        image (uploaded file)
 *
 * Success response:
 *   { "success": 1, "file": { "url": "/uploads/<filename>" } }
 *
 * Error response:
 *   { "success": 0, "error": { "message": "<reason>" } }
 */
class ImageUploadController
{
    private const UPLOADS_SUBPATH = '/uploads/';
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const RANDOM_SUFFIX_BYTES = 12;

    public function upload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['image'] ?? null;

        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonError($response, 'No valid file was uploaded.', 400);
        }

        $mediaType = $file->getClientMediaType();
        if (!is_string($mediaType) || !in_array($mediaType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->jsonError($response, 'Only JPEG, PNG, GIF, and WebP images are allowed.', 422);
        }

        try {
            $directory = $this->ensureUploadsDirectory();
            $filename  = $this->generateFilename($file);
            $file->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
        } catch (\RuntimeException $e) {
            return $this->jsonError($response, 'Failed to save the uploaded file.', 500);
        }

        $url = rtrim(self::UPLOADS_SUBPATH, '/') . '/' . $filename;

        $response->getBody()->write(json_encode([
            'success' => 1,
            'file'    => ['url' => $url],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function ensureUploadsDirectory(): string
    {
        $dir = $this->getPublicRoot() . self::UPLOADS_SUBPATH;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Unable to create upload directory "%s".', $dir));
        }

        return rtrim($dir, '/\\');
    }

    private function generateFilename(UploadedFileInterface $file): string
    {
        $original  = $file->getClientFilename() ?? '';
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $extension = 'jpg';
        }

        return sprintf('%s.%s', bin2hex(random_bytes(self::RANDOM_SUFFIX_BYTES)), $extension);
    }

    private function getPublicRoot(): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        return rtrim($root, '/\\') . '/www';
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode([
            'success' => 0,
            'error'   => ['message' => $message],
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
