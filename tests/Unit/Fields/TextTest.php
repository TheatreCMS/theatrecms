<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Fields;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheatreCMS\Fields\Types\Text;

/**
 * @coversDefaultClass \TheatreCMS\Fields\Types\Text
 */
class TextTest extends TestCase
{
    public function testTypeIsText(): void
    {
        $this->assertSame('text', Text::TYPE);
    }

    public function testAValueRoundTripsUnchanged(): void
    {
        $text = new Text();
        $stored = $text->serialize('Hello, world');

        $this->assertSame('Hello, world', $stored);
        $this->assertSame('Hello, world', $text->deserialize($stored));
    }

    public function testAStringIsValid(): void
    {
        $this->assertSame([], (new Text())->validate('Hello'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonStrings(): array
    {
        return [
            'int' => [42],
            'float' => [1.5],
            'bool' => [true],
            'array' => [['Hello']],
            'object' => [new stdClass()],
        ];
    }

    #[DataProvider('nonStrings')]
    public function testANonStringHasOneError(mixed $value): void
    {
        $this->assertCount(1, (new Text())->validate($value));
    }
}
