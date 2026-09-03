<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Image;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Venue;

/**
 * Regression guard for the featured_image_id FK migration: Production, Post,
 * Season, and Venue all replaced a plain `featured_image_url` string column
 * with a ManyToOne relation to Image (see SupportsFeaturedImage removal).
 * Each must still resolve getFeaturedImageUrl() through the relation, and
 * return null (not throw) when no image is attached.
 */
class FeaturedImageMappingTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping integration test.');
        }

        $paths = [__DIR__ . '/../../src/Models'];
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function persistImage(): Image
    {
        $image = new Image('/uploads/test.jpg', 'test.jpg');
        $this->em->persist($image);
        $this->em->flush();

        return $image;
    }

    public function testProductionResolvesFeaturedImageUrlThroughRelation(): void
    {
        $season = new Season('2025-2026', '2025-2026 Season');
        $this->em->persist($season);

        $production = new Production('A Show', $season);
        $production->setSlug('a-show');
        $this->em->persist($production);
        $this->em->flush();

        $this->assertNull($production->getFeaturedImageUrl());
        $this->assertFalse($production->hasFeaturedImage());

        $image = $this->persistImage();
        $production->setFeaturedImage($image);
        $this->em->flush();

        $this->assertSame('/uploads/test.jpg', $production->getFeaturedImageUrl());
        $this->assertTrue($production->hasFeaturedImage());
    }

    public function testPostResolvesFeaturedImageUrlThroughRelation(): void
    {
        $post = new Post('A Post', ContentStatus::DRAFT, 'body');
        $post->setSlug('a-post');
        $this->em->persist($post);
        $this->em->flush();

        $this->assertNull($post->getFeaturedImageUrl());

        $image = $this->persistImage();
        $post->setFeaturedImage($image);
        $this->em->flush();

        $this->assertSame('/uploads/test.jpg', $post->getFeaturedImageUrl());
    }

    public function testSeasonResolvesFeaturedImageUrlThroughRelation(): void
    {
        $season = new Season('2025-2026', '2025-2026 Season');
        $this->em->persist($season);
        $this->em->flush();

        $this->assertNull($season->getFeaturedImageUrl());

        $image = $this->persistImage();
        $season->setFeaturedImage($image);
        $this->em->flush();

        $this->assertSame('/uploads/test.jpg', $season->getFeaturedImageUrl());
    }

    public function testVenueResolvesFeaturedImageUrlThroughRelation(): void
    {
        $venue = new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000');
        $venue->setSlug('main-stage');
        $this->em->persist($venue);
        $this->em->flush();

        $this->assertNull($venue->getFeaturedImageUrl());
        $this->assertFalse($venue->hasFeaturedImage());

        $image = $this->persistImage();
        $venue->setFeaturedImage($image);
        $this->em->flush();

        $this->assertSame('/uploads/test.jpg', $venue->getFeaturedImageUrl());
        $this->assertTrue($venue->hasFeaturedImage());
    }
}
