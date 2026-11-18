<?php

namespace TheatreCMS\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\VenueRepository;
use TheatreCMS\Traits\HandlesContentImages;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Theme\TemplateResolver;
use TheatreCMS\Traits\RendersPublicContent;

/**
 * @extends BaseController<VenueRepository>
 */
class VenueController extends BaseController implements PublicContentController, HasAdditionalAdminRoutes
{
    use RendersPublicContent;
    use HandlesContentImages;

    /** Form labels for VenueRepository::REQUIRED_FIELDS, as the venue forms show them. */
    private const REQUIRED_FIELD_LABELS = [
        'name'     => 'Name',
        'address'  => 'Address',
        'city'     => 'City',
        'state'    => 'State',
        'postcode' => 'Postcode',
    ];

    public static function additionalAdminRoutes(): array
    {
        return [
            ['get', '/quick-create', 'quickCreate'],
            ['post', '/quick-create', 'quickStore'],
        ];
    }

    public function __construct(
        VenueRepository $repository,
        EntityManagerInterface $em,
        Twig $twig,
        private readonly ContentMetaRepository $contentMeta,
        private readonly TemplateResolver $templateResolver,
    ) {
        parent::__construct($repository, $twig, $em);
    }

    protected function templateResolver(): TemplateResolver
    {
        return $this->templateResolver;
    }

    protected function contentType(): string
    {
        return 'venue';
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render(
            $response,
            'admin/venues/index.html.twig',
            $this->buildPaginatedViewData($request, $this->repository, 'venues', '/admin/venues')
        );
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/venues/create.html.twig');
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $venue = $this->repository->fetch($args['id']);

        return $this->twig->render($response, 'admin/venues/edit.html.twig', [
            'supportsHeroImage' => $this->supportsHeroImage($request),
            'venue' => $venue,
            'heroImageId' => $this->contentMeta->get($this->contentType(), $venue->getId(), self::HERO_IMAGE_META_KEY),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $venue = $this->repository->fetch(intval($args['id']));
        if ($venue) {
            $this->repository->delete($venue);
        }

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render(
                $response,
                'admin/venues/_list.html.twig',
                $this->buildPaginatedViewData($request, $this->repository, 'venues', '/admin/venues')
            );
        }

        return $this->buildListRedirect($response, $request, '/admin/venues');
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create venue. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'name' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'postcode' => null,
            'capacity' => null,
            'description' => null,
            'accessibilityInfo' => null,
            'websiteUrl' => null,
            'mapUrl' => null,
            'featuredImageId' => null,
            'heroImageId' => null,
            'heroText'    => null,
        ]);

        $missing = $this->missingRequiredFields($data);
        if ($missing !== []) {
            return $this->missingFieldsResponse($request, $response, $missing);
        }

        $venue = $this->repository->create($data);
        $this->applyFeaturedImage($venue, $data['featuredImageId']);
        $this->applyHeroImageIfSupported($request, $venue->getId(), $data['heroImageId']);
        $this->applyHeroTextIfSupported($request, $venue->getId(), $data['heroText']);
        $this->entityManager->flush();
        $editUrl = '/admin/venues/edit/' . $venue->getId();

        if ($request->getHeaderLine('HX-Request')) {
            return $response->withHeader('HX-Redirect', $editUrl);
        }

        return $response->withHeader('Location', $editUrl);
    }

    public function quickCreate(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/venues/_quick_create_modal.html.twig');
    }

    public function quickStore(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'error',
                'message' => 'Unable to create venue. Please check your input.',
            ]);
        }

        $data = $this->parseArgs($data, [
            'name'     => null,
            'address'  => null,
            'city'     => null,
            'state'    => null,
            'postcode' => null,
        ]);

        $missing = $this->missingRequiredFields($data);
        if ($missing !== []) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'error',
                'message' => $this->missingFieldsMessage($missing),
            ]);
        }

        $venue = $this->repository->create($data);

        $trigger = json_encode([
            'entityCreated' => [
                'id'   => $venue->getId(),
                'name' => $venue->getName(),
                'type' => 'venue',
            ],
        ]);

        $response = $this->twig->render($response, 'admin/partials/_alert.html.twig', [
            'type'    => 'success',
            'message' => $venue->getName() . ' was added.',
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
                    'message' => 'Unable to save venue. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'venueId' => 0,
            'name' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'postcode' => null,
            'capacity' => null,
            'description' => null,
            'accessibilityInfo' => null,
            'websiteUrl' => null,
            'mapUrl' => null,
            'featuredImageId' => null,
            'heroImageId' => null,
            'heroText'    => null,
        ]);

        $venue = $this->repository->fetch(intval($data['venueId']));

        if (is_null($venue)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Venue not found.',
                ]);
            }
            return $response->withStatus(404);
        }

        $missing = $this->missingRequiredFields($data);
        if ($missing !== []) {
            return $this->missingFieldsResponse($request, $response, $missing);
        }

        $venue->setName($data['name'])
            ->setAddress($data['address'])
            ->setCity($data['city'])
            ->setState($data['state'])
            ->setPostcode($data['postcode'])
            ->setCapacity(is_numeric($data['capacity']) ? intval($data['capacity']) : null)
            ->setDescription($data['description'])
            ->setAccessibilityInfo($data['accessibilityInfo'])
            ->setWebsiteUrl($data['websiteUrl'])
            ->setMapUrl($data['mapUrl']);

        $this->applyFeaturedImage($venue, $data['featuredImageId']);
        $this->applyHeroImageIfSupported($request, $venue->getId(), $data['heroImageId']);
        $this->applyHeroTextIfSupported($request, $venue->getId(), $data['heroText']);

        $this->repository->update($venue);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Venue saved successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/venues');
    }

    /**
     * Labels of the required fields that are missing or blank. The forms mark these
     * `required`, but a request can still arrive without them, and the Venue model can't
     * be built without them.
     *
     * @param array<string, mixed> $data
     * @return string[]
     */
    private function missingRequiredFields(array $data): array
    {
        $missing = [];
        foreach (VenueRepository::REQUIRED_FIELDS as $field) {
            if (!is_string($data[$field] ?? null) || trim($data[$field]) === '') {
                $missing[] = self::REQUIRED_FIELD_LABELS[$field];
            }
        }

        return $missing;
    }

    /**
     * @param string[] $missing labels from missingRequiredFields()
     */
    private function missingFieldsMessage(array $missing): string
    {
        return 'Please fill in: ' . implode(', ', $missing) . '.';
    }

    /**
     * An error alert for an HTMX request, otherwise a 400 with the message as its body.
     *
     * @param string[] $missing labels from missingRequiredFields()
     */
    private function missingFieldsResponse(Request $request, Response $response, array $missing): Response
    {
        $message = $this->missingFieldsMessage($missing);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'error',
                'message' => $message,
            ]);
        }

        $response->getBody()->write($message);

        return $response->withStatus(400);
    }
}
