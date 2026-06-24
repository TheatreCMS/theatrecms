<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response as SlimResponse;
use Slim\Views\Twig;
use TheatreCMS\Controllers\EventController;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\VenueRepository;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class EventControllerTest extends TestCase
{
    private EventRepository|MockObject $eventRepo;
    private EntityManagerInterface|MockObject $entityManager;
    private Twig|MockObject $twig;
    private ProductionRepository|MockObject $productionRepo;
    private VenueRepository $venueRepo;

    protected function setUp(): void
    {
        $this->eventRepo = $this->createMock(EventRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->twig = $this->createMock(Twig::class);
        $this->productionRepo = $this->createMock(ProductionRepository::class);
        $this->venueRepo = new VenueRepository($this->entityManager);
    }

    public function testStoreRecurringReturns400WhenNoBody(): void
    {
        $controller = $this->buildController();

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([]);
        $request->method('getHeaderLine')->with('HX-Request')->willReturn('');

        $response = $this->createMock(Response::class);
        $response->method('withStatus')->with(400)->willReturn($response);

        $this->eventRepo->expects($this->never())->method('createRecurring');

        $result = $controller->storeRecurring($request, $response);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testStoreRecurringRendersHtmxErrorWhenValidationFails(): void
    {
        $controller = $this->buildController();

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'productionId' => 9,
            'weekdays' => [
                'thursday' => [
                    'selected' => '1',
                    'time' => '',
                ],
            ],
        ]);
        $request->method('getHeaderLine')->with('HX-Request')->willReturn('true');

        $response = $this->createMock(Response::class);
        $response->method('withStatus')->with(400)->willReturn($response);

        $this->eventRepo->expects($this->never())->method('createRecurring');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                $this->isInstanceOf(Response::class),
                'admin/partials/_alert.html.twig',
                $this->callback(static function (array $context): bool {
                    return $context['type'] === 'error'
                        && $context['message'] === 'A start time is required for Thursday.';
                })
            )
            ->willReturn($this->createMock(Response::class));

        $result = $controller->storeRecurring($request, $response);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testStoreRecurringRendersSuccessSummaryForHtmx(): void
    {
        $controller = $this->buildController();
        $production = $this->createMock(Production::class);
        $venueEntityRepository = $this->createMock(EntityRepository::class);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'productionId' => 12,
            'weekdays' => [
                'thursday' => [
                    'selected' => '1',
                    'time' => '20:00',
                ],
                'friday' => [
                    'selected' => '1',
                    'time' => '20:00',
                ],
            ],
        ]);
        $request->method('getHeaderLine')->with('HX-Request')->willReturn('true');

        $response = new SlimResponse();

        $this->eventRepo->expects($this->once())
            ->method('createRecurring')
            ->with([
                'productionId' => 12,
                'weekdays' => [
                    'thursday' => '20:00',
                    'friday' => '20:00',
                ],
            ])
            ->willReturn([
                'created' => 6,
                'skipped' => 2,
            ]);

        $this->productionRepo->expects($this->once())
            ->method('fetch')
            ->with(12)
            ->willReturn($production);

        $this->eventRepo->expects($this->once())
            ->method('fetchByProduction')
            ->with(12)
            ->willReturn([]);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Venue::class)
            ->willReturn($venueEntityRepository);

        $venueEntityRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->twig->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnCallback(function (string $template, array $context): string {
                if ($template === 'admin/partials/_alert.html.twig') {
                    $this->assertSame('success', $context['type']);
                    $this->assertSame('Created 6 performances. Skipped 2 duplicates.', $context['message']);

                    return '<div>success</div>';
                }

                $this->assertSame('admin/productions/_events.html.twig', $template);
                $this->assertSame([], $context['events']);
                $this->assertSame(12, $context['productionId']);
                $this->assertTrue($context['oobSwap']);

                return '<section id="production-events-table"></section>';
            });

        $this->twig->expects($this->never())
            ->method('render');

        $result = $controller->storeRecurring($request, $response);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertStringContainsString('success', (string) $result->getBody());
        $this->assertStringContainsString('production-events-table', (string) $result->getBody());
    }

    public function testEditPassesBackToProductionUrlWhenOpenedFromProductionPerformances(): void
    {
        $controller = $this->buildController();
        $event = $this->createMock(\TheatreCMS\Models\Event::class);

        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn([
            'returnTo' => 'production-performances',
            'productionId' => '12',
        ]);

        $response = $this->createMock(Response::class);

        $this->eventRepo->expects($this->once())
            ->method('fetch')
            ->with('44')
            ->willReturn($event);

        $this->productionRepo->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Venue::class)
            ->willReturn($this->createMock(EntityRepository::class));

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                $this->isInstanceOf(Response::class),
                'admin/events/edit.html.twig',
                $this->callback(static function (array $context) use ($event): bool {
                    return $context['event'] === $event
                        && $context['backToProductionUrl'] === '/admin/productions/edit/12?tab=performances#performances';
                })
            )
            ->willReturn($this->createMock(Response::class));

        $result = $controller->edit($request, $response, ['id' => '44']);

        $this->assertInstanceOf(Response::class, $result);
    }

    private function buildController(): EventController
    {
        return new EventController(
            $this->eventRepo,
            $this->entityManager,
            $this->twig,
            $this->productionRepo,
            $this->venueRepo
        );
    }
}
