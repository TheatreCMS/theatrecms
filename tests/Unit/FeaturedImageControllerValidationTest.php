<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Controllers\BaseController;
use TheatreCMS\Controllers\PostController;
use TheatreCMS\Controllers\ProductionController;
use TheatreCMS\Controllers\SeasonController;
use TheatreCMS\Controllers\VenueController;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Media;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Venue;

class FeaturedImageControllerValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, Post|Production|Season|Venue}>
     */
    public static function controllerAndEntityProvider(): iterable
    {
        $season = new Season('2026', '2026 Season');

        yield 'post' => [
            PostController::class,
            new Post('News', ContentStatus::DRAFT, '{}'),
        ];
        yield 'production' => [
            ProductionController::class,
            new Production('Hamlet', $season),
        ];
        yield 'season' => [
            SeasonController::class,
            new Season('2027', '2027 Season'),
        ];
        yield 'venue' => [
            VenueController::class,
            new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000'),
        ];
    }

    #[DataProvider('controllerAndEntityProvider')]
    public function testApplyFeaturedImageRejectsNonImageMedia(string $controllerClass, object $entity): void
    {
        $existingImage = new Media('/uploads/existing.jpg', 'existing.jpg', Media::TYPE_IMAGE);
        $entity->setFeaturedImage($existingImage);
        $controller = $this->controllerWithMedia(
            $controllerClass,
            new Media('/uploads/program.pdf', 'program.pdf', Media::TYPE_PDF)
        );

        try {
            $this->invokeApplyFeaturedImage($controller, $entity, 42);
            $this->fail('Expected a non-image featured media assignment to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Featured media must be an image.', $exception->getMessage());
        }

        $this->assertSame($existingImage, $entity->getFeaturedImage());
    }

    #[DataProvider('controllerAndEntityProvider')]
    public function testApplyFeaturedImageAcceptsImageMedia(string $controllerClass, object $entity): void
    {
        $image = new Media('/uploads/photo.jpg', 'photo.jpg', Media::TYPE_IMAGE);
        $controller = $this->controllerWithMedia($controllerClass, $image);

        $this->invokeApplyFeaturedImage($controller, $entity, 42);

        $this->assertSame($image, $entity->getFeaturedImage());
    }

    #[DataProvider('controllerAndEntityProvider')]
    public function testApplyFeaturedImageStillClearsMedia(string $controllerClass, object $entity): void
    {
        $entity->setFeaturedImage(new Media('/uploads/photo.jpg', 'photo.jpg', Media::TYPE_IMAGE));
        $controller = $this->controllerWithMedia($controllerClass, null);

        $this->invokeApplyFeaturedImage($controller, $entity, '');

        $this->assertNull($entity->getFeaturedImage());
    }

    private function controllerWithMedia(string $controllerClass, ?Media $media): object
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(42)->willReturn($media);

        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Media::class)->willReturn($repository);

        $reflection = new \ReflectionClass($controllerClass);
        $controller = $reflection->newInstanceWithoutConstructor();

        $entityManagerProperty = new \ReflectionProperty(BaseController::class, 'entityManager');
        $entityManagerProperty->setValue($controller, $entityManager);

        return $controller;
    }

    private function invokeApplyFeaturedImage(object $controller, object $entity, mixed $mediaId): void
    {
        $method = new \ReflectionMethod($controller, 'applyFeaturedImage');
        $method->invoke($controller, $entity, $mediaId);
    }
}
