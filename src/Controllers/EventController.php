<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Models\Event;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\VenueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Psr7\Stream;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints\Date as DateConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @method EventRepository repository()
 */
class EventController extends BaseController
{
    private ProductionRepository $productionRepo;
    private VenueRepository $venueRepo;

    public function __construct(
        EventRepository $repository,
        EntityManagerInterface $em,
        Twig $twig,
        ProductionRepository $productionRepo,
        VenueRepository $venueRepo
    ) {
        $this->repository     = $repository;
        $this->entityManager  = $em;
        $this->twig           = $twig;
        $this->productionRepo = $productionRepo;
        $this->venueRepo      = $venueRepo;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/events/index.html.twig', [
            'events' => $this->repository->fetchAll(),
        ]);
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/events/create.html.twig', [
            'productions' => $this->productionRepo->fetchAll(),
            'venues'      => $this->venueRepo->fetchAll(),
        ]);
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $queryParams = $request->getQueryParams();

        return $this->twig->render($response, 'admin/events/edit.html.twig', [
            'event'               => $this->repository->fetch($args['id']),
            'productions'         => $this->productionRepo->fetchAll(),
            'venues'              => $this->venueRepo->fetchAll(),
            'backToProductionUrl' => $this->buildBackToProductionUrl($queryParams),
        ]);
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response->withStatus(400), 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create event. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data['slug'] = '';

        try {
            $this->repository->create($data);
        } catch (\InvalidArgumentException $e) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response->withStatus(400), 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create event. Please check your input.',
                ]);
            }
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }

        if ($request->getHeaderLine('HX-Request')) {
            if (!empty($data['productionId'])) {
                return $this->freshProductionEventsResponse(
                    $response,
                    (int) $data['productionId'],
                    'success',
                    'Event created successfully.'
                );
            }

            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Event created successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/events');
    }

    public function storeRecurring(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response->withStatus(400), 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create recurring performances. Please check your input.',
                ]);
            }

            return $response->withStatus(400);
        }

        try {
            $result = $this->repository->createRecurring([
                'productionId' => $data['productionId'] ?? null,
                'weekdays'     => $this->extractRecurringWeekdays(
                    isset($data['weekdays']) && is_array($data['weekdays']) ? $data['weekdays'] : []
                ),
            ]);
        } catch (\InvalidArgumentException $e) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response->withStatus(400), 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => $e->getMessage(),
                ]);
            }

            $response->getBody()->write($e->getMessage());

            return $response->withStatus(400);
        }

        $message = $this->formatRecurringCreateMessage(
            (int) ($result['created'] ?? 0),
            (int) ($result['skipped'] ?? 0)
        );

        if ($request->getHeaderLine('HX-Request')) {
            return $this->freshProductionEventsResponse(
                $response,
                (int) ($data['productionId'] ?? 0),
                'success',
                $message
            );
        }

        $productionId = (int) ($data['productionId'] ?? 0);

        return $response->withHeader('Location', sprintf('/admin/productions/edit/%d#performances', $productionId));
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save event. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'eventId' => 0,
            'productionId' => null,
            'venueId' => null,
            'startsAt' => null,
            'status' => 'scheduled',
            'ticketUrl' => null,
            'notes' => null,
            'title' => null,
        ]);

        $item = $this->repository->fetch($data['eventId']);
        if (!$item) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Event not found.',
                ]);
            }
            return $response->withStatus(404);
        }

        if (!empty($data['productionId'])) {
            $production = $this->entityManager->getRepository(Production::class)->find((int)$data['productionId']);
            if (!$production) {
                if ($request->getHeaderLine('HX-Request')) {
                    return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                        'type'    => 'error',
                        'message' => 'Unable to save event. Please check your input.',
                    ]);
                }
                return $response->withStatus(400);
            }
            $item->setProduction($production);
        }

        if (isset($data['venueId'])) {
            if (!empty($data['venueId'])) {
                $venue = $this->entityManager->getRepository(Venue::class)->find((int)$data['venueId']);
                if (!$venue) {
                    if ($request->getHeaderLine('HX-Request')) {
                        return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                            'type'    => 'error',
                            'message' => 'Unable to save event. Please check your input.',
                        ]);
                    }
                    return $response->withStatus(400);
                }
                $item->setVenue($venue);
            } else {
                $item->setVenue(null);
            }
        }

        try {
            $startsAt = new \DateTimeImmutable($data['startsAt']);
            $item->setStartsAt($startsAt);
        } catch (\Exception $e) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save event. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $item->setStatus($data['status']);
        $item->setTicketUrl($data['ticketUrl']);
        $item->setNotes($data['notes']);
        $item->setTitle($data['title']);

        $this->repository->update($item);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Event saved successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/events');
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $event = $this->repository->fetch($args['id']);
        try {
            if ($event) {
                $this->repository->delete($event);
            }
        } catch (\Exception $e) {
            trigger_error("Unable to delete event: {$e->getMessage()}");
        }

        $data = ['events' => $this->repository->fetchAll()];

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/events/_table.html.twig', $data);
        }

        return $this->twig->render($response, 'admin/events/index.html.twig', $data);
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('startsAt', new NotBlank());
        $metadata->addPropertyConstraint('startsAt', new DateConstraint());
    }

    private function extractRecurringWeekdays(array $weekdays): array
    {
        $selections = [];

        foreach ($weekdays as $weekday => $config) {
            if (!is_array($config) || empty($config['selected'])) {
                continue;
            }

            $normalizedWeekday = strtolower(trim((string) $weekday));
            $time = trim((string) ($config['time'] ?? ''));

            if ($time === '') {
                throw new \InvalidArgumentException(
                    sprintf('A start time is required for %s.', ucfirst($normalizedWeekday))
                );
            }

            $selections[$normalizedWeekday] = $time;
        }

        return $selections;
    }

    private function formatRecurringCreateMessage(int $created, int $skipped): string
    {
        $performanceLabel = $created === 1 ? 'performance' : 'performances';
        $message = sprintf('Created %d %s.', $created, $performanceLabel);

        if ($skipped > 0) {
            $duplicateLabel = $skipped === 1 ? 'duplicate' : 'duplicates';
            $message .= sprintf(' Skipped %d %s.', $skipped, $duplicateLabel);
        }

        return $message;
    }

    private function freshProductionEventsResponse(
        Response $response,
        int $productionId,
        string $type,
        string $message
    ): Response {
        $production = $this->productionRepo->fetch($productionId);

        if (!$production instanceof Production) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => $type,
                'message' => $message,
            ]);
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $this->twig->fetch('admin/partials/_alert.html.twig', [
            'type'    => $type,
            'message' => $message,
        ]));
        fwrite($stream, $this->twig->fetch('admin/productions/_events.html.twig', [
            'events'       => $this->repository->fetchByProduction($productionId),
            'venues'       => $this->venueRepo->fetchAll(),
            'productionId' => $productionId,
            'production'   => $production,
            'oobSwap'      => true,
        ]));
        rewind($stream);

        return $response->withBody(new Stream($stream));
    }

    private function buildBackToProductionUrl(array $queryParams): ?string
    {
        if (($queryParams['returnTo'] ?? null) !== 'production-performances') {
            return null;
        }

        $productionId = (int) ($queryParams['productionId'] ?? 0);
        if ($productionId <= 0) {
            return null;
        }

        return sprintf('/admin/productions/edit/%d?tab=performances#performances', $productionId);
    }
}
