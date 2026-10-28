<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Hooks\HookManager;
use TheatreCMS\Models\Post;
use TheatreCMS\Theme\ExcerptResolver;

/**
 * Themes filter excerpts through `theatrecms/the_excerpt` on the frontend; the model getter the
 * admin reads stays unfiltered.
 */
class ExcerptResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HookManager::setInstance(new HookManager());
    }

    public function testResolveAppliesTheExcerptFilterButTheGetterDoesNot(): void
    {
        HookManager::getInstance()->addFilter('theatrecms/the_excerpt', fn(string $excerpt): string => "$excerpt!");
        $post = (new Post('News', ContentStatus::DRAFT, '{}'))->setExcerpt('Short');

        $this->assertSame('Short!', (new ExcerptResolver())->resolve($post));
        $this->assertSame('Short', $post->getExcerpt());
    }

    public function testResolveReturnsAnEmptyStringWhenThereIsNoExcerpt(): void
    {
        $post = new Post('News', ContentStatus::DRAFT, '{}');

        $this->assertSame('', (new ExcerptResolver())->resolve($post));
    }
}
