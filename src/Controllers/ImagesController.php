<?php

namespace TheatreCMS\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;
use TheatreCMS\Repositories\ImageRepository;
use TheatreCMS\Services\ImageUploadService;

/**
 * Admin media library: browsing/searching previously uploaded images and
 * uploading new ones for use as a featured image on Productions, Posts,
 * Seasons, and Venues.
 *
 * Distinct from ImageUploadController, which serves the EditorJS in-body
 * "Image Gallery" block at POST /admin/images/upload and must keep its
 * existing JSON contract untouched.
 *
 * @method ImageRepository repository()
 */
class ImagesController extends BaseController
{
    private const SORTABLE_COLUMNS = ['filename', 'uploadedAt', 'sizeBytes'];

    private ImageUploadService $imageUploadService;

    public function __construct(ImageRepository $repository, Twig $twig, ImageUploadService $imageUploadService)
    {
        $this->repository = $repository;
        $this->twig = $twig;
        $this->imageUploadService = $imageUploadService;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        [$search, $sort, $direction] = $this->resolveListQuery($request, self::SORTABLE_COLUMNS);
        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'images',
            '/admin/images',
            [],
            $search,
            $sort,
            $direction
        );

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/images/_grid.html.twig', $data);
        }

        return $this->twig->render($response, 'admin/images/index.html.twig', $data);
    }

    public function picker(Request $request, Response $response, array $args = []): Response
    {
        [$search] = $this->resolveListQuery($request, []);
        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'images',
            '/admin/images/picker',
            ['target' => (string) ($request->getQueryParams()['target'] ?? '')],
            $search
        );

        return $this->twig->render($response, 'admin/images/_picker.html.twig', $data);
    }

    public function upload(Request $request, Response $response, array $args = []): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if (
            !$file instanceof UploadedFileInterface
            || $file->getError() !== UPLOAD_ERR_OK
            || !$this->imageUploadService->isImage($file)
        ) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'error',
                'message' => 'Please choose a valid image file.',
            ]);
        }

        $url = $this->imageUploadService->store($file);

        $image = $this->repository->create([
            'url'              => $url,
            'filename'         => basename($url),
            'originalFilename' => $file->getClientFilename(),
            'mimeType'         => $file->getClientMediaType(),
            'sizeBytes'        => $file->getSize(),
        ]);

        return $this->twig->render($response, 'admin/partials/_featured_image_selection.html.twig', [
            'image' => $image,
        ]);
    }

    public function select(Request $request, Response $response, array $args = []): Response
    {
        $image = $this->repository->fetch((int) ($args['id'] ?? 0));

        if ($image === null) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/partials/_featured_image_selection.html.twig', [
            'image' => $image,
        ]);
    }
}
