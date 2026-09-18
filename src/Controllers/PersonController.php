<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Traits\HandlesContentImages;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * @extends BaseController<PersonRepository>
 */
class PersonController extends BaseController
{
    use HandlesContentImages;

    public function __construct(
        PersonRepository $repository,
        Twig $twig,
        EntityManagerInterface $em,
        private readonly ContentMetaRepository $contentMeta
    ) {
        parent::__construct($repository, $twig, $em);
    }

    protected function contentType(): string
    {
        return 'person';
    }

    protected function routePrefix(): string
    {
        return 'people';
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        [$search] = $this->resolveListQuery($request, []);
        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'people',
            '/admin/people',
            [],
            $search
        );

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/people/_list.html.twig', $data);
        }

        return $this->twig->render($response, 'admin/people/index.html.twig', $data);
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/people/create.html.twig');
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $person = $this->repository->fetch($args['id']);

        return $this->twig->render($response, 'admin/people/edit.html.twig', [
            'person' => $person,
            'heroImageId' => $this->contentMeta->get($this->contentType(), $person->getId(), self::HERO_IMAGE_META_KEY),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $person = $this->repository->fetch(intval($args['id']));
        if ($person) {
            $this->repository->delete($person);
        }

        $data = $this->buildPaginatedViewData($request, $this->repository, 'people', '/admin/people');

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/people/_list.html.twig', $data);
        }

        return $this->buildListRedirect($response, $request, '/admin/people');
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create person. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'firstName' => null,
            'lastName'  => null,
            'biography' => null,
            'headshotUrl' => null,
            'featuredImageId' => null,
            'heroImageId' => null,
        ]);

        $person = $this->repository->create($data);
        $this->applyFeaturedImage($person, $data['featuredImageId']);
        $this->applyHeroImage($person->getId(), $data['heroImageId']);
        $this->entityManager->flush();
        $editUrl = '/admin/people/edit/' . $person->getId();

        if ($request->getHeaderLine('HX-Request')) {
            return $response->withHeader('HX-Redirect', $editUrl);
        }

        return $response->withHeader('Location', $editUrl);
    }

    public function quickCreate(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/people/_quick_create_modal.html.twig');
    }

    public function quickStore(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'error',
                'message' => 'Unable to create person. Please check your input.',
            ]);
        }

        $data = $this->parseArgs($data, [
            'firstName' => null,
            'lastName'  => null,
        ]);

        $person = $this->repository->create($data);

        $trigger = json_encode([
            'entityCreated' => [
                'id'   => $person->getId(),
                'name' => $person->getName(),
                'type' => 'person',
            ],
        ]);

        $response = $this->twig->render($response, 'admin/partials/_alert.html.twig', [
            'type'    => 'success',
            'message' => $person->getName() . ' was added.',
        ]);

        return $response->withHeader('HX-Trigger', $trigger);
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save person. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'personId' => 0,
            'firstName' => null,
            'lastName' => null,
            'biography' => null,
            'headshotUrl' => null,
            'featuredImageId' => null,
            'heroImageId' => null,
        ]);

        $person = $this->repository->fetch(intval($data['personId']));

        if (is_null($person)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Person not found.',
                ]);
            }
            return $response->withStatus(404);
        }

        $person->setFirstName($data['firstName'])
            ->setLastName($data['lastName'])
            ->setBiography($data['biography'])
            ->setHeadshotUrl(filter_var($data['headshotUrl'], FILTER_VALIDATE_URL) ?: $data['headshotUrl']);

        $this->applyFeaturedImage($person, $data['featuredImageId']);
        $this->applyHeroImage($person->getId(), $data['heroImageId']);

        $this->repository->update($person);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Person saved successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/people');
    }
}
