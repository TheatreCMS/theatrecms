<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Repositories\SponsorRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;

class SponsorController extends BaseController
{
    private const UPLOADS_SUBPATH = '/uploads/';
    private const RANDOM_SUFFIX_BYTES = 12;

    protected SponsorRepository $sponsorRepository;

    public function __construct(SponsorRepository $repository, Twig $twig)
    {
        $this->sponsorRepository = $repository;
        $this->twig              = $twig;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render(
            $response,
            'admin/sponsors/index.html.twig',
            $this->buildPaginatedViewData($request, $this->sponsorRepository, 'sponsors', '/admin/sponsors')
        );
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/sponsors/create.html.twig');
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/sponsors/edit.html.twig', [
            'sponsor' => $this->sponsorRepository->fetch($args['id']),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $sponsor = $this->sponsorRepository->fetch(intval($args['id']));
        if ($sponsor) {
            $this->sponsorRepository->delete($sponsor);
        }

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render(
                $response,
                'admin/sponsors/_list.html.twig',
                $this->buildPaginatedViewData($request, $this->sponsorRepository, 'sponsors', '/admin/sponsors')
            );
        }

        return $this->buildListRedirect($response, $request, '/admin/sponsors');
    }

    public function removeLogo(Request $request, Response $response, array $args = []): Response
    {
        $sponsor = $this->sponsorRepository->fetch(intval($args['id']));

        if (is_null($sponsor)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Sponsor not found.',
                ]);
            }
            return $response->withStatus(404);
        }

        $this->deleteLogoFile($sponsor->getLogoUrl());
        $sponsor->setLogoUrl('');
        $this->sponsorRepository->update($sponsor);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/sponsors/_logo_removed.html.twig', [
                'sponsor' => $sponsor,
            ]);
        }

        return $response->withHeader('Location', '/admin/sponsors/edit/' . $sponsor->getId());
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create sponsor. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'name' => null,
            'logoUrl' => null,
            'websiteUrl' => null,
        ]);

        $this->sponsorRepository->create($data);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Sponsor created successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/sponsors');
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save sponsor. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'sponsorId' => 0,
            'name' => null,
            'logoUrl' => null,
            'websiteUrl' => null,
        ]);

        $logoUploadUrl = $this->storeLogoUpload($request);
        if ($logoUploadUrl !== null) {
            $data['logoUrl'] = $logoUploadUrl;
        }

        $sponsor = $this->sponsorRepository->fetch(intval($data['sponsorId']));

        if (is_null($sponsor)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Sponsor not found.',
                ]);
            }
            return $response->withStatus(404);
        }

        $sponsor->setName($data['name'])
            ->setLogoUrl($data['logoUrl'])
            ->setWebsiteUrl($data['websiteUrl']);

        $this->sponsorRepository->update($sponsor);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/sponsors/_saved.html.twig', [
                'sponsor' => $sponsor,
            ]);
        }

        return $response->withHeader('Location', '/admin/sponsors');
    }

    private function storeLogoUpload(Request $request): ?string
    {
        $uploadedFiles = $request->getUploadedFiles();
        $logo = $uploadedFiles['logoImage'] ?? null;

        if (!$logo instanceof UploadedFileInterface || $logo->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($logo->getError() !== UPLOAD_ERR_OK || !$this->isImageUpload($logo)) {
            return null;
        }

        $directory = $this->ensureUploadsDirectory();
        $filename = $this->generateUploadFilename($logo);
        $logo->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return rtrim(self::UPLOADS_SUBPATH, '/') . '/' . $filename;
    }

    private function deleteLogoFile(?string $url): void
    {
        if (!$url) {
            return;
        }

        $path = $this->resolveLogoPath($url);
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }

    private function resolveLogoPath(string $url): ?string
    {
        if (!str_starts_with($url, self::UPLOADS_SUBPATH)) {
            return null;
        }

        $relative = ltrim($url, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return $this->getPublicRoot() . '/' . $relative;
    }

    private function isImageUpload(UploadedFileInterface $file): bool
    {
        $mediaType = $file->getClientMediaType();
        return is_string($mediaType) && str_starts_with($mediaType, 'image/');
    }

    private function ensureUploadsDirectory(): string
    {
        $directory = $this->getPublicRoot() . self::UPLOADS_SUBPATH;

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create upload directory "%s".', $directory));
        }

        return rtrim($directory, '/\\');
    }

    private function generateUploadFilename(UploadedFileInterface $file): string
    {
        $original = $file->getClientFilename() ?? '';
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
}
