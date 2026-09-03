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
use TheatreCMS\Models\Image;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\VenueRepository;

/**
 * Venue is the net-new featured-image entity added alongside the media
 * library; these pin down the two behaviors that changed shape from the
 * old per-entity upload flow: removeFeaturedImage() now only detaches the
 * relation (it must NOT delete the underlying Image/file, since the same
 * image can be shared by other entities), and store()/update() resolve a
 * submitted featuredImageId to an Image via the EntityManager.
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
        $image = new Image('/uploads/venue.jpg', 'venue.jpg');
        $venue = new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000');
        $venue->setFeaturedImage($image);

        $this->mockVenueLookup($venue, 5);

        $request = $this->createMock(Request::class);
        $request->method('getHeaderLine')->with('HX-Request')->willReturn('');
        $response = $this->createMock(Response::class);
        $response->method('withHeader')->willReturn($response);

        $controller = $this->buildController();
        $controller->removeFeaturedImage($request, $response, ['id' => '5']);

        $this->assertNull($venue->getFeaturedImage());
        // The Image row itself is untouched: the relation was cleared, not the entity.
        $this->assertSame('/uploads/venue.jpg', $image->getUrl());
    }

    public function testUpdateResolvesFeaturedImageIdToAnImageEntity(): void
    {
        $venue = new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000');
        $image = new Image('/uploads/new.jpg', 'new.jpg');

        $venueObjectRepository = $this->createMock(EntityRepository::class);
        $venueObjectRepository->method('findOneBy')->willReturn($venue);
        $imageObjectRepository = $this->createMock(EntityRepository::class);
        $imageObjectRepository->method('find')->with(42)->willReturn($image);
        $this->entityManager->method('getRepository')->willReturnCallback(
            function (string $class) use ($venueObjectRepository, $imageObjectRepository) {
                return $class === Image::class ? $imageObjectRepository : $venueObjectRepository;
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

        $this->assertSame($image, $venue->getFeaturedImage());
    }

    public function testUpdateClearsFeaturedImageWhenIdIsEmpty(): void
    {
        $venue = new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000');
        $venue->setFeaturedImage(new Image('/uploads/old.jpg', 'old.jpg'));

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
