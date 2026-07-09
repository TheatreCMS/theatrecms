<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Repositories\SponsorRepository;
use TheatreCMS\Services\ImageUploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;

class SponsorController extends BaseController
{
    protected SponsorRepository $sponsorRepository;
    private ImageUploadService $imageUploadService;

    public function __construct(SponsorRepository $repository, Twig $twig, ImageUploadService $imageUploadService)
    {
        $this->sponsorRepository  = $repository;
        $this->twig               = $twig;
        $this->imageUploadService = $imageUploadService;
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

        $this->imageUploadService->delete($sponsor->getLogoUrl());
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

        if ($logo->getError() !== UPLOAD_ERR_OK || !$this->imageUploadService->isImage($logo)) {
            return null;
        }

        return $this->imageUploadService->store($logo);
    }
}
