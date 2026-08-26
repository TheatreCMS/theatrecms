<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Page;
use TheatreCMS\Models\Post;
use TheatreCMS\Theme\SlugResolver;
use TheatreCMS\Twig\SlugExtension;

class SlugResolverTest extends TestCase
{
    public function testResolvesSlugFromEntity(): void
    {
        $page = new Page('A Page', \TheatreCMS\Enums\ContentStatus::PUBLISHED, 'body');
        $page->setSlug('a-page');

        $this->assertSame('a-page', (new SlugResolver())->resolve($page));
    }

    public function testExtensionReturnsStringSlug(): void
    {
        $post = new Post('A Post', \TheatreCMS\Enums\ContentStatus::PUBLISHED, 'body');
        $post->setSlug('a-post');

        $result = (new SlugExtension(new SlugResolver()))->theSlug($post);

        $this->assertSame('a-post', $result);
    }
}
