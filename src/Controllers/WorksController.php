<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Models\Work;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Repositories\WorkRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class WorksController extends BaseController
{
    private PersonRepository $personRepo;

    public function __construct(WorkRepository $repository, Twig $twig, PersonRepository $personRepo)
    {
        $this->repository = $repository;
        $this->twig       = $twig;
        $this->personRepo = $personRepo;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render(
            $response,
            'admin/works/index.html.twig',
            $this->buildPaginatedViewData($request, $this->repository, 'works', '/admin/works')
        );
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/works/create.html.twig', [
            'people' => $this->personRepo->fetchAll(),
        ]);
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $work = $this->repository->fetch($args['id']);
        $creatorEntries = [];
        if ($work) {
            foreach ($work->getWorkCreators() as $wc) {
                $creatorEntries[] = [
                    'personId' => $wc->person()->getId(),
                    'role'     => $wc->role(),
                ];
            }
        }

        return $this->twig->render($response, 'admin/works/edit.html.twig', [
            'work'           => $work,
            'people'         => $this->personRepo->fetchAll(),
            'creatorEntries' => $creatorEntries,
        ]);
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create work. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        if (($creatorEntries = $this->collectCreatorEntries($data)) !== null) {
            $data['creators'] = $creatorEntries;
        }

        $this->repository->create($data);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Work created successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/works');
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        $data = array_merge([
            'id' => null,
            'title' => null,
            'description' => null,
            'synopsis' => null,
            'creators' => null,
        ], $data);

        $workId = $data['id'] ?? null;

        if (!$workId) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save work. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $work = $this->repository->fetch(intval($workId));

        if (!$work) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Work not found.',
                ]);
            }
            return $response->withStatus(404);
        }

        if (($creatorEntries = $this->collectCreatorEntries($data)) !== null) {
            $data['creators'] = $creatorEntries;
        }

        /** @var Work $workEntity */
        $workEntity = $work;

        $this->repository->updateFromArgs($workEntity, $data);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Work saved successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/works');
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $work = $this->repository->fetch((int) ($args['id'] ?? 0));

        if ($work) {
            $this->repository->delete($work);
        }

        $data = $this->buildPaginatedViewData($request, $this->repository, 'works', '/admin/works');

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/works/_list.html.twig', $data);
        }

        return $this->buildListRedirect($response, $request, '/admin/works');
    }

    private function collectCreatorEntries(array $data): ?array
    {
        if (array_key_exists('creatorIds', $data) && is_array($data['creatorIds'])) {
            $roles = $data['creatorRoles'] ?? [];
            return $this->normalizeCreatorRows($data['creatorIds'], $roles);
        }

        if (!empty($data['creators']) && is_array($data['creators'])) {
            $roles = $data['creators_roles'] ?? [];
            return $this->normalizeLegacyCreatorEntries($data['creators'], $roles);
        }

        return null;
    }

    private function normalizeCreatorRows(array $ids, array $roles): array
    {
        $entries = [];

        foreach ($ids as $index => $rawId) {
            $creatorId = intval($rawId);
            if (!$creatorId) {
                continue;
            }

            $role = '';
            if (isset($roles[$index])) {
                $role = trim(strval($roles[$index]));
            }

            $entries[] = ['id' => $creatorId, 'role' => $role];
        }

        return $entries;
    }

    private function normalizeLegacyCreatorEntries(array $creatorIds, array $roles): array
    {
        $entries = [];

        foreach ($creatorIds as $rawId) {
            $creatorId = intval($rawId);
            if (!$creatorId) {
                continue;
            }

            $role = '';
            if (isset($roles[$creatorId])) {
                $role = trim(strval($roles[$creatorId]));
            }

            $entries[] = ['id' => $creatorId, 'role' => $role];
        }

        return $entries;
    }
}
