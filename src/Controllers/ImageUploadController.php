<?php

namespace TheatreCMS\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use TheatreCMS\Models\Media;
use TheatreCMS\Services\MediaImporter;
use TheatreCMS\Services\RemoteImageFetcher;

/**
 * Handles EditorJS-compatible image uploads for the image, gallery and carousel blocks.
 * Uploads are added to the media library (MediaImporter), like any other upload.
 *
 * POST /admin/images/upload
 *   Content-Type: multipart/form-data
 *   Field:        image (uploaded file)
 *
 * POST /admin/images/fetch (the image block's byUrl endpoint, used when an image URL is pasted)
 *   Content-Type: application/json
 *   Body:         { "url": "https://..." }
 *   Downloads the image and stores it like an upload.
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

    public function __construct(
        private readonly MediaImporter $mediaImporter,
        private readonly RemoteImageFetcher $remoteImageFetcher
    ) {
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

        return $this->storeFile($response, $file);
    }

    public function fetch(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $url = is_array($body) && is_string($body['url'] ?? null) ? trim($body['url']) : '';

        if ($url === '') {
            return $this->jsonError($response, 'A URL is required.', 400);
        }

        try {
            $file = $this->remoteImageFetcher->fetch($url);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($response, $e->getMessage(), 422);
        }

        return $this->storeFile($response, $file);
    }

    private function storeFile(Response $response, UploadedFileInterface $file): Response
    {
        try {
            $url = $this->mediaImporter->import($file, Media::TYPE_IMAGE)->getUrl();
        } catch (\RuntimeException | \InvalidArgumentException $e) {
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
