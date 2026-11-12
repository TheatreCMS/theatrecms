<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Fields;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheatreCMS\Fields\Types\Select;

/**
 * @coversDefaultClass \TheatreCMS\Fields\Types\Select
 */
class SelectTest extends TestCase
{
    private const OPTIONS = ['comedy' => 'Comedy', 'drama' => 'Drama'];

    public function testTypeIsSelect(): void
    {
        $this->assertSame('select', Select::TYPE);
    }

    public function testAValueRoundTripsUnchanged(): void
    {
        $select = new Select(self::OPTIONS);
        $stored = $select->serialize('drama');

        $this->assertSame('drama', $stored);
        $this->assertSame('drama', $select->deserialize($stored));
    }

    public function testAnOptionsValueIsValid(): void
    {
        $this->assertSame([], (new Select(self::OPTIONS))->validate('comedy'));
    }

    public function testANumericOptionsValueIsValidAsAString(): void
    {
        // PHP turns the key '2026' into the int 2026; a submitted value is still the string.
        $select = new Select(['2026' => '2026 season', 'archive' => 'Archive']);

        $this->assertSame([], $select->validate('2026'));
    }

    public function testAValueNotInTheOptionsHasOneError(): void
    {
        $this->assertCount(1, (new Select(self::OPTIONS))->validate('musical'));
    }

    public function testAnOptionsLabelIsNotAValidValue(): void
    {
        $this->assertCount(1, (new Select(self::OPTIONS))->validate('Comedy'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonStrings(): array
    {
        return [
            'int' => [42],
            'bool' => [true],
            'array' => [['comedy']],
            'object' => [new stdClass()],
        ];
    }

    #[DataProvider('nonStrings')]
    public function testANonStringHasOneError(mixed $value): void
    {
        $this->assertCount(1, (new Select(self::OPTIONS))->validate($value));
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function invalidOptions(): array
    {
        return [
            'empty' => [[]],
            'plain list' => [['Comedy', 'Drama']],
            'empty value' => [['' => 'None', 'comedy' => 'Comedy']],
            'empty label' => [['comedy' => '', 'drama' => 'Drama']],
            'non-string label' => [['comedy' => 1, 'drama' => 'Drama']],
        ];
    }

    /**
     * @param array<mixed> $options
     */
    #[DataProvider('invalidOptions')]
    public function testInvalidOptionsThrow(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Select($options);
    }
}
