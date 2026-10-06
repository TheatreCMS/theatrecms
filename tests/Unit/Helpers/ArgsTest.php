<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Helpers\Args;

class ArgsTest extends TestCase
{
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
}
