<?php

use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Theme\ContentTypeRegistry;
use TheatreCMS\Theme\PermalinkResolver;
use TheatreCMS\Theme\TemplateResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * @var TemplateResolver $resolver
 * @var ContentTypeRegistry $contentTypes
 */

// Groups a flat, startsAt-ordered list of events by calendar day, returning
// array<string, array{date: \DateTimeImmutable, events: TheatreCMS\Models\Event[]}>.
$groupCalendarEventsByDate = static function (array $events): array {
    $grouped = [];

    foreach ($events as $event) {
        $key = $event->getStartsAt()->format('Y-m-d');

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'date' => $event->getStartsAt(),
                'events' => [],
            ];
        }

        $grouped[$key]['events'][] = $event;
    }

    return $grouped;
};

if (isset($app) && $contentTypes->hasArchive('calendar')) {
    $app->group('/' . $contentTypes->prefix('calendar'), function ($group) use (
        $resolver,
        $contentTypes,
        $groupCalendarEventsByDate
    ) {
        $container = $group->getContainer();

        $group->get('/events.json', function (Request $request, Response $response) use ($container) {
            /** @var EventRepository $eventRepository */
            $eventRepository = $container->get(EventRepository::class);
            /** @var PermalinkResolver $permalinkResolver */
            $permalinkResolver = $container->get(PermalinkResolver::class);

            $params = $request->getQueryParams();

            try {
                $start = new \DateTimeImmutable($params['start'] ?? 'first day of this month');
                $end = new \DateTimeImmutable($params['end'] ?? 'first day of next month');
            } catch (\Exception $e) {
                $response->getBody()->write(json_encode(['error' => 'Invalid start/end date.']));

                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $events = $eventRepository->fetchInRange($start, $end);

            $feed = array_map(static function ($event) use ($permalinkResolver) {
                $production = $event->getProduction();
                $venue = $event->getEffectiveVenue();

                // Intentionally omit the UTC offset here (unlike DATE_ATOM) so FullCalendar
                // treats these as literal wall-clock times rather than converting them to
                // the browser's local zone — see calendar.js. There is no app-level
                // timezone configured anywhere in TheatreCMS to convert from/to correctly.
                $payload = [
                    'id' => $event->getId(),
                    'title' => $event->getTitle() ?: ($production?->getName() ?? ($venue?->getName() ?? 'Event')),
                    'start' => $event->getStartsAt()->format('Y-m-d\TH:i:s'),
                    'url' => $permalinkResolver->resolve($event),
                    'extendedProps' => [
                        'venue' => $venue?->getName(),
                        'status' => $event->getStatus(),
                        'ticketUrl' => $event->getEffectiveTicketUrl(),
                    ],
                ];

                if ($event->getEndsAt() !== null) {
                    $payload['end'] = $event->getEndsAt()->format('Y-m-d\TH:i:s');
                }

                if (in_array($event->getStatus(), ['cancelled', 'canceled'], true)) {
                    $payload['classNames'] = ['fc-event-cancelled'];
                }

                return $payload;
            }, $events);

            $response->getBody()->write(json_encode($feed));

            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->get('/events/{id}', function (Request $request, Response $response, array $args) use ($container) {
            /** @var EventRepository $eventRepository */
            $eventRepository = $container->get(EventRepository::class);
            $event = $eventRepository->fetch((int) $args['id']);

            if (!$event) {
                $response->getBody()->write('Event not found');

                return $response->withStatus(404);
            }

            $event = apply_filters('theatrecms/event', $event, $request, $args);

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'calendar/_event-detail.html.twig', ['event' => $event]);
        });

        $group->get('/events', function (
            Request $request,
            Response $response
        ) use (
            $container,
            $groupCalendarEventsByDate
        ) {
            /** @var EventRepository $eventRepository */
            $eventRepository = $container->get(EventRepository::class);

            $params = $request->getQueryParams();

            try {
                $start = new \DateTimeImmutable($params['start'] ?? 'first day of this month');
                $end = new \DateTimeImmutable($params['end'] ?? 'first day of next month');
            } catch (\Exception $e) {
                $start = new \DateTimeImmutable('first day of this month');
                $end = new \DateTimeImmutable('first day of next month');
            }

            $events = $eventRepository->fetchInRange($start, $end);
            $events = apply_filters('theatrecms/calendar_events', $events, $request);

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'calendar/_event-list.html.twig', [
                'groupedEvents' => $groupCalendarEventsByDate($events),
            ]);
        });

        $group->get('', function (
            Request $request,
            Response $response
        ) use (
            $container,
            $resolver,
            $contentTypes,
            $groupCalendarEventsByDate
        ) {
            /** @var EventRepository $eventRepository */
            $eventRepository = $container->get(EventRepository::class);

            $start = new \DateTimeImmutable('first day of this month');
            $end = new \DateTimeImmutable('first day of +2 months');

            $events = $eventRepository->fetchInRange($start, $end);
            $events = apply_filters('theatrecms/calendar_events', $events, $request);

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $title = $contentTypes->label('calendar');

            return $resolver->renderList($twig, $response, 'calendar', $title, $events, [
                'groupedEvents' => $groupCalendarEventsByDate($events),
            ]);
        });
    })->add(new RequireTwigMiddleware($app->getContainer()));
}
