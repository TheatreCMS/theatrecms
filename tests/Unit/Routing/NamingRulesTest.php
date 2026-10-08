<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Routing;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Routing\NamingRules;

class NamingRulesTest extends TestCase
{
    public function testAcceptsLowercaseLettersAndUnderscoresInTypeName(): void
    {
        NamingRules::assertTypeName('press_release', 'content type');

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTypeNames(): array
    {
        return [
            'empty' => [''],
            'uppercase' => ['Press_release'],
            'digit' => ['season_2026'],
            'hyphen' => ['press-release'],
            'space' => ['press release'],
        ];
    }

    #[DataProvider('invalidTypeNames')]
    public function testRejectsInvalidTypeName(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid taxonomy name');

        NamingRules::assertTypeName($name, 'taxonomy');
    }

    public function testAcceptsLowercaseLettersAndHyphensInUrlPrefix(): void
    {
        NamingRules::assertUrlPrefix('press-releases', 'content type "press_release"');

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrlPrefixes(): array
    {
        return [
            'empty' => [''],
            'uppercase' => ['Shows'],
            'digit' => ['top-10'],
            'underscore' => ['press_releases'],
            'slash' => ['shows/past'],
        ];
    }

    #[DataProvider('invalidUrlPrefixes')]
    public function testRejectsInvalidUrlPrefix(string $urlPrefix): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('for content type "seasons"');

        NamingRules::assertUrlPrefix($urlPrefix, 'content type "seasons"');
    }

    public function testRejectsReservedUrlPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');

        NamingRules::assertUrlPrefix('admin', 'taxonomy "genre"');
    }
}
