<?php

namespace TheatreCMS\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;
use TheatreCMS\Models\Media;
use TheatreCMS\Repositories\MediaRepository;
use TheatreCMS\Services\MediaTypeClassifier;
use TheatreCMS\Services\MediaUploadService;

/**
 * Admin media library: browsing/searching/uploading images, PDFs, audio, and
 * video files for use as content attachments (currently: the Post/Production/
 * Season/Venue featured-image flow, images-only).
 *
 * Distinct from ImageUploadController, which serves the EditorJS in-body
 * "Image Gallery" block at POST /admin/images/upload and must keep its
 * existing JSON contract untouched.
 *
 * @method MediaRepository repository()
 */
class MediaController extends BaseController
{
    private const SORTABLE_COLUMNS = ['filename', 'uploadedAt', 'sizeBytes'];

    private const UNSUPPORTED_TYPE_MESSAGE = 'Unsupported file type. Allowed: images (JPG, PNG, GIF, WebP), '
        . 'PDF, audio (MP3, WAV, OGG, M4A), video (MP4, WebM, MOV).';

    private MediaUploadService $mediaUploadService;

    public function __construct(MediaRepository $repository, Twig $twig, MediaUploadService $mediaUploadService)
    {
        $this->repository = $repository;
        $this->twig = $twig;
        $this->mediaUploadService = $mediaUploadService;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        [$search, $sort, $direction] = $this->resolveListQuery($request, self::SORTABLE_COLUMNS);
        $type = $this->resolveRequestedType($request, '');

        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'media',
            '/admin/media',
            ['type' => $type],
            $search,
            $sort,
            $direction,
            ['type' => $type]
        );

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/media/_grid.html.twig', $data);
        }

        return $this->twig->render($response, 'admin/media/index.html.twig', $data);
    }

    public function picker(Request $request, Response $response, array $args = []): Response
    {
        [$search] = $this->resolveListQuery($request, []);
        $type = $this->resolveRequestedType($request, Media::TYPE_IMAGE);

        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'media',
            '/admin/media/picker',
            [
                'target' => (string) ($request->getQueryParams()['target'] ?? ''),
                'type' => $type,
            ],
            $search,
            '',
            'asc',
            ['type' => $type]
        );

        return $this->twig->render($response, 'admin/media/_picker.html.twig', $data);
    }

    public function upload(Request $request, Response $response, array $args = []): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'error',
                'message' => 'Please choose a file to upload.',
            ]);
        }

        $extension = pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION);
        $mediaType = MediaTypeClassifier::classify($file->getClientMediaType(), $extension);

        if ($mediaType === Media::TYPE_OTHER) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'error',
                'message' => self::UNSUPPORTED_TYPE_MESSAGE,
            ]);
        }

        $url = $this->mediaUploadService->store($file);

        $media = $this->repository->create([
            'url'              => $url,
            'filename'         => basename($url),
            'originalFilename' => $file->getClientFilename(),
            'mimeType'         => $file->getClientMediaType(),
            'sizeBytes'        => $file->getSize(),
            'mediaType'        => $mediaType,
        ]);

        // Uploaded directly from the library page (not the featured-image
        // picker modal): refresh the grid out-of-band instead of rendering
        // the picker's selection partial, which targets DOM ids that don't
        // exist there.
        if ($request->getHeaderLine('HX-Target') === 'media-upload-result') {
            [$search, $sort, $direction] = $this->resolveListQuery($request, self::SORTABLE_COLUMNS);
            $type = $this->resolveRequestedType($request, '');

            $data = $this->buildPaginatedViewData(
                $request,
                $this->repository,
                'media',
                '/admin/media',
                ['type' => $type, 'oob' => true],
                $search,
                $sort,
                $direction,
                ['type' => $type]
            );

            return $this->twig->render($response, 'admin/media/_grid.html.twig', $data);
        }

        return $this->twig->render($response, 'admin/partials/_featured_media_selection.html.twig', [
            'media' => $media,
        ]);
    }

    public function select(Request $request, Response $response, array $args = []): Response
    {
        $media = $this->repository->fetch((int) ($args['id'] ?? 0));

        if ($media === null) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/partials/_featured_media_selection.html.twig', [
            'media' => $media,
        ]);
    }

    public function show(Request $request, Response $response, array $args = []): Response
    {
        $media = $this->repository->fetch((int) ($args['id'] ?? 0));

        if ($media === null) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/media/_details.html.twig', [
            'media' => $media,
        ]);
    }

    public function updateAltText(Request $request, Response $response, array $args = []): Response
    {
        $media = $this->repository->fetch((int) ($args['id'] ?? 0));

        if ($media === null) {
            return $response->withStatus(404);
        }

        $data = (array) $request->getParsedBody();
        $media->setAltText(trim((string) ($data['altText'] ?? '')) ?: null);
        $this->repository->update($media);

        return $this->twig->render($response, 'admin/media/_details.html.twig', [
            'media'   => $media,
            'saved'   => true,
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $media = $this->repository->fetch((int) ($args['id'] ?? 0));

        if ($media) {
            $this->mediaUploadService->delete($media->getUrl());
            $this->repository->delete($media);
        }

        if ($request->getHeaderLine('HX-Request')) {
            [$search, $sort, $direction] = $this->resolveListQuery($request, self::SORTABLE_COLUMNS);
            $type = $this->resolveRequestedType($request, '');

            return $this->twig->render($response, 'admin/media/_grid.html.twig', $this->buildPaginatedViewData(
                $request,
                $this->repository,
                'media',
                '/admin/media',
                ['type' => $type],
                $search,
                $sort,
                $direction,
                ['type' => $type]
            ));
        }

        return $this->buildListRedirect($response, $request, '/admin/media');
    }

    private function resolveRequestedType(Request $request, string $default): string
    {
        $type = (string) ($request->getQueryParams()['type'] ?? $default);

        return in_array($type, Media::ALL_TYPES, true) ? $type : $default;
    }
}
