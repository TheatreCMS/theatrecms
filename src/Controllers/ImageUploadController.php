<?php

namespace TheatreCMS\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use TheatreCMS\Services\ImageUploadService;

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
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private readonly ImageUploadService $imageUploadService)
    {
    }

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
            $url = $this->imageUploadService->store($file);
        } catch (\RuntimeException $e) {
            return $this->jsonError($response, 'Failed to save the uploaded file.', 500);
        }

        $response->getBody()->write(json_encode([
            'success' => 1,
            'file'    => ['url' => $url],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
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
