<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Helpers\Args;
use TheatreCMS\Tests\Includes\CapturesUserNotices;

class ArgsTest extends TestCase
{
    use CapturesUserNotices;

    public function testSuppliedValueOverridesDefault(): void
    {
        $args = Args::parse(['title' => 'Season announced'], ['title' => '']);

        $this->assertSame('Season announced', $args['title']);
    }

    public function testMissingKeyTakesDefault(): void
    {
        $args = Args::parse([], ['status' => 'draft']);

        $this->assertSame('draft', $args['status']);
    }

    public function testExplicitNullIsKept(): void
    {
        $args = Args::parse(['excerpt' => null], ['excerpt' => 'Default excerpt']);

        $this->assertArrayHasKey('excerpt', $args);
        $this->assertNull($args['excerpt']);
    }

    public function testUnknownKeyIsKept(): void
    {
        $args = Args::parse(['menu_icon' => 'star'], ['title' => '']);

        $this->assertSame(['title' => '', 'menu_icon' => 'star'], $args);
    }

    public function testWithoutUnknownKeysKeepsAllowedKeys(): void
    {
        [$args, $levels] = $this->captureUserNotices(
            static fn() => Args::withoutUnknownKeys(
                ['label' => 'News', 'public' => false],
                ['label', 'public'],
                'content type'
            )
        );

        $this->assertSame(['label' => 'News', 'public' => false], $args);
        $this->assertSame([], $levels);
    }

    public function testWithoutUnknownKeysDropsEachUnknownKeyWithANotice(): void
    {
        [$args, $levels] = $this->captureUserNotices(
            static fn() => Args::withoutUnknownKeys(
                ['label' => 'News', 'url_prefix' => 'news', 'icon' => 'star'],
                ['label'],
                'taxonomy'
            )
        );

        $this->assertSame(['label' => 'News'], $args);
        $this->assertSame([E_USER_NOTICE, E_USER_NOTICE], $levels);
    }
}
