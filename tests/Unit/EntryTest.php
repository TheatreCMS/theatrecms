<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Entry;

/**
 * @coversDefaultClass \TheatreCMS\Models\Entry
 */
class EntryTest extends TestCase
{
    public function testConstructorStoresTheGivenArgs(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-02 03:04:05');
        $modifiedAt = new DateTimeImmutable('2026-02-03 04:05:06');

        $entry = new Entry([
            'contentType' => 'press_release',
            'title' => 'Season Announced',
            'slug' => 'season-announced',
            'status' => ContentStatus::DRAFT,
            'content' => '{"blocks":[]}',
            'excerpt' => 'Our new season',
            'createdAt' => $createdAt,
            'modifiedAt' => $modifiedAt,
        ]);

        $this->assertSame('press_release', $entry->getContentType());
        $this->assertSame('Season Announced', $entry->getTitle());
        $this->assertSame('season-announced', $entry->getSlug());
        $this->assertSame(ContentStatus::DRAFT, $entry->getStatus());
        $this->assertSame('{"blocks":[]}', $entry->getContent());
        $this->assertSame('Our new season', $entry->getExcerpt());
        $this->assertSame($createdAt, $entry->getCreatedAt());
        $this->assertSame($modifiedAt, $entry->getModifiedAt());
    }

    public function testDefaults(): void
    {
        $entry = new Entry(['contentType' => 'press_release', 'title' => 'Launch', 'slug' => 'launch']);

        $this->assertSame(ContentStatus::DRAFT, $entry->getStatus());
        $this->assertNull($entry->getContent());
        $this->assertNull($entry->getExcerpt());
        $this->assertNull($entry->getPublishedAt());
    }

    public function testAGivenPublishedAtIsKeptForAPublishedEntry(): void
    {
        $publishedAt = new DateTimeImmutable('2019-05-01 12:00:00');

        $entry = new Entry([
            'contentType' => 'press_release',
            'title' => 'Archive post',
            'slug' => 'archive-post',
            'status' => ContentStatus::PUBLISHED,
            'publishedAt' => $publishedAt,
        ]);

        $this->assertSame($publishedAt, $entry->getPublishedAt());
    }

    public function testAPublishedEntryWithoutPublishedAtIsPublishedNow(): void
    {
        $before = new DateTimeImmutable();

        $entry = new Entry([
            'contentType' => 'press_release',
            'title' => 'Launch',
            'slug' => 'launch',
            'status' => ContentStatus::PUBLISHED,
        ]);

        $after = new DateTimeImmutable();
        $this->assertNotNull($entry->getPublishedAt());
        $this->assertGreaterThanOrEqual($before, $entry->getPublishedAt());
        $this->assertLessThanOrEqual($after, $entry->getPublishedAt());
    }

    public function testADraftWithoutPublishedAtHasNone(): void
    {
        $entry = new Entry([
            'contentType' => 'press_release',
            'title' => 'Launch',
            'slug' => 'launch',
            'status' => ContentStatus::DRAFT,
        ]);

        $this->assertNull($entry->getPublishedAt());
    }

    public function testSetters(): void
    {
        $entry = new Entry(['contentType' => 'press_release', 'title' => 'Launch', 'slug' => 'launch']);

        $entry->setTitle('Relaunch')
            ->setSlug('relaunch')
            ->setContent('{"blocks":[]}')
            ->setExcerpt('Again');

        $this->assertSame('Relaunch', $entry->getTitle());
        $this->assertSame('relaunch', $entry->getSlug());
        $this->assertSame('{"blocks":[]}', $entry->getContent());
        $this->assertSame('Again', $entry->getExcerpt());
    }
}
