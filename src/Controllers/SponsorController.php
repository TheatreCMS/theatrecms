<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Repositories\SponsorRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class SponsorController extends BaseController
{
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
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Sponsor saved successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/sponsors');
    }
}
