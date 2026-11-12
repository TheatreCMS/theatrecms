<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\ContentType\ContentRepositoryLocator;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Controllers\SponsorController;
use TheatreCMS\Controllers\VenueController;
use TheatreCMS\Controllers\WorksController;
use TheatreCMS\Repositories\EntryRepository;
use TheatreCMS\Repositories\WorkRepository;

class ContentRepositoryLocatorTest extends TestCase
{
    public function testFindsMappedAndEntryRepositoriesForPublicTypesOnly(): void
    {
        $contentTypes = new ContentTypeRegistry();
        $contentTypes->register('work', ['controller' => WorksController::class]);
        $contentTypes->register('venue', ['controller' => VenueController::class]);
        $contentTypes->register('sponsor', ['controller' => SponsorController::class, 'public' => false]);
        $contentTypes->register('press_release');

        $works = $this->createStub(WorkRepository::class);
        $releases = $this->createStub(EntryRepository::class);
        $entries = $this->createStub(EntryRepository::class);
        $entries->method('forType')->willReturnMap([['press_release', $releases]]);

        $locator = new ContentRepositoryLocator($contentTypes, $entries, [
            'work' => $works,
            'sponsor' => $this->createStub(WorkRepository::class),
        ]);

        $this->assertSame($works, $locator->forType('work'));
        $this->assertSame($releases, $locator->forType('press_release'));
        $this->assertNull($locator->forType('venue'), 'model-backed type with no mapped repository');
        $this->assertNull($locator->forType('sponsor'), 'non-public type');
        $this->assertNull($locator->forType('concert'), 'unregistered type');
        $this->assertSame(['work', 'press_release'], array_keys($locator->publicTypes()));
    }
}
