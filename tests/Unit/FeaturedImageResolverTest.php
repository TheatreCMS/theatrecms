<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Post;
use TheatreCMS\Theme\FeaturedImageResolver;
use TheatreCMS\Twig\FeaturedImageExtension;

class FeaturedImageResolverTest extends TestCase
{
    public function testResolvesFeaturedImageUrlFromEntity(): void
    {
        $post = new Post('A Post', ContentStatus::PUBLISHED, 'body');
        $post->setFeaturedImageUrl('/uploads/a-post.jpg');

        $this->assertSame('/uploads/a-post.jpg', (new FeaturedImageResolver())->resolve($post));
    }

    public function testResolvesEmptyStringWhenEntityHasNoFeaturedImage(): void
    {
        $post = new Post('A Post', ContentStatus::PUBLISHED, 'body');

        $this->assertSame('', (new FeaturedImageResolver())->resolve($post));
    }

    public function testThrowsWhenEntityCannotResolveAFeaturedImage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FeaturedImageResolver())->resolve(new \stdClass());
    }

    public function testExtensionReturnsStringUrl(): void
    {
        $post = new Post('A Post', ContentStatus::PUBLISHED, 'body');
        $post->setFeaturedImageUrl('/uploads/a-post.jpg');

        $result = (new FeaturedImageExtension(new FeaturedImageResolver()))->theFeaturedImageUrl($post);

        $this->assertSame('/uploads/a-post.jpg', $result);
    }
}
