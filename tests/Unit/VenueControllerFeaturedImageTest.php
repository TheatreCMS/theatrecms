<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Controllers\VenueController;
use TheatreCMS\Models\Media;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\VenueRepository;

/**
 * Venue is the net-new featured-image entity added alongside the media
 * library; these pin down the two behaviors that changed shape from the
 * old per-entity upload flow: removeFeaturedImage() now only detaches the
 * relation (it must NOT delete the underlying Media/file, since the same
 * media row can be shared by other entities), and store()/update() resolve a
 * submitted featuredImageId to a Media entity via the EntityManager.
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class VenueControllerFeaturedImageTest extends TestCase
{
    // VenueRepository is `final`, so it can't be mocked directly; wrap a real
    // instance around a mocked EntityManagerInterface instead (same approach
    // EventControllerTest uses for VenueRepository).
    private VenueRepository $venueRepo;
    private EntityManagerInterface|MockObject $entityManager;
    private Twig|MockObject $twig;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->venueRepo = new VenueRepository($this->entityManager);
        $this->twig = $this->createMock(Twig::class);
    }

    private function buildController(): VenueController
    {
        return new VenueController($this->venueRepo, $this->entityManager, $this->twig);
    }

    private function mockVenueLookup(Venue $venue, int $id): void
    {
        $venueObjectRepository = $this->createMock(EntityRepository::class);
        $venueObjectRepository->method('findOneBy')->with(['id' => $id])->willReturn($venue);
        $this->entityManager->method('getRepository')->willReturnCallback(
            function (string $class) use ($venueObjectRepository) {
                return $class === Venue::class ? $venueObjectRepository : $this->createMock(EntityRepository::class);
            }
        );
    }

    public function testRemoveFeaturedImageDetachesWithoutDeletingTheImage(): void
    {
        $media = new Media('/uploads/venue.jpg', 'venue.jpg', Media::TYPE_IMAGE);
        $venue = new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000');
        $venue->setFeaturedImage($media);

        $this->mockVenueLookup($venue, 5);

        $request = $this->createMock(Request::class);
        $request->method('getHeaderLine')->with('HX-Request')->willReturn('');
        $response = $this->createMock(Response::class);
        $response->method('withHeader')->willReturn($response);

        $controller = $this->buildController();
        $controller->removeFeaturedImage($request, $response, ['id' => '5']);

        $this->assertNull($venue->getFeaturedImage());
        // The Media row itself is untouched: the relation was cleared, not the entity.
        $this->assertSame('/uploads/venue.jpg', $media->getUrl());
    }

    public function testUpdateResolvesFeaturedImageIdToAnImageEntity(): void
    {
        $venue = new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000');
        $media = new Media('/uploads/new.jpg', 'new.jpg', Media::TYPE_IMAGE);

        $venueObjectRepository = $this->createMock(EntityRepository::class);
        $venueObjectRepository->method('findOneBy')->willReturn($venue);
        $mediaObjectRepository = $this->createMock(EntityRepository::class);
        $mediaObjectRepository->method('find')->with(42)->willReturn($media);
        $this->entityManager->method('getRepository')->willReturnCallback(
            function (string $class) use ($venueObjectRepository, $mediaObjectRepository) {
                return $class === Media::class ? $mediaObjectRepository : $venueObjectRepository;
            }
        );

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'venueId' => 1,
            'name' => 'Main Stage',
            'address' => '1 Stage Rd',
            'city' => 'Testville',
            'state' => 'TS',
            'postcode' => '00000',
            'featuredImageId' => '42',
        ]);
        $request->method('getHeaderLine')->with('HX-Request')->willReturn('');
        $response = $this->createMock(Response::class);
        $response->method('withHeader')->willReturn($response);

        $controller = $this->buildController();
        $controller->update($request, $response, []);

        $this->assertSame($media, $venue->getFeaturedImage());
    }

    public function testUpdateClearsFeaturedImageWhenIdIsEmpty(): void
    {
        $venue = new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000');
        $venue->setFeaturedImage(new Media('/uploads/old.jpg', 'old.jpg', Media::TYPE_IMAGE));

        $this->mockVenueLookup($venue, 1);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'venueId' => 1,
            'name' => 'Main Stage',
            'address' => '1 Stage Rd',
            'city' => 'Testville',
            'state' => 'TS',
            'postcode' => '00000',
            'featuredImageId' => '',
        ]);
        $request->method('getHeaderLine')->with('HX-Request')->willReturn('');
        $response = $this->createMock(Response::class);
        $response->method('withHeader')->willReturn($response);

        $controller = $this->buildController();
        $controller->update($request, $response, []);

        $this->assertNull($venue->getFeaturedImage());
    }
}
