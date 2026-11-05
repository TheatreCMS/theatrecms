<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Entry;
use TheatreCMS\Theme\DateResolver;
use TheatreCMS\Twig\DateExtension;

class DateResolverTest extends TestCase
{
    private DateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DateResolver();
    }

    public function testResolvesPublishedDateWhenPresent(): void
    {
        $post = new Entry(['contentType' => 'post', 'title' => 'A Post', 'status' => ContentStatus::PUBLISHED, 'content' => '{}']);
        $post->setPublishedAt(new DateTimeImmutable('2026-08-26 10:30:00'));

        $this->assertSame('August 26, 2026', $this->resolver->resolve($post));
        $this->assertSame('2026-08-26', $this->resolver->resolve($post, 'Y-m-d'));
    }

    public function testFallsBackToCreatedAtWhenUnpublished(): void
    {
        $post = new Entry(['contentType' => 'post', 'title' => 'Draft Post', 'status' => ContentStatus::DRAFT, 'content' => '{}']);

        $this->assertSame($post->getCreatedAt()->format('Y-m-d'), $this->resolver->resolve($post, 'Y-m-d'));
    }

    public function testThrowsForUnmappedEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver->resolve(new \stdClass());
    }

    public function testExtensionDelegatesToResolver(): void
    {
        $extension = new DateExtension($this->resolver);
        $post = new Entry(['contentType' => 'post', 'title' => 'A Post', 'status' => ContentStatus::PUBLISHED, 'content' => '{}']);
        $post->setPublishedAt(new DateTimeImmutable('2026-08-26 10:30:00'));

        $this->assertSame('August 26, 2026', $extension->theDate($post));
    }
}