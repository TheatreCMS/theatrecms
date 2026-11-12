<?php

namespace TheatreCMS\Fields\Types;

use TheatreCMS\Fields\FieldType;

/**
 * @param array<string, string> $options The list of selectable options. A value/label pair for each option.
 */
class Select implements FieldType
{
    public const string TYPE = 'select';

    public function __construct(protected array $options)
    {
        if (!$this->validateOptions($this->options)) {
            throw new \InvalidArgumentException('Invalid options provided.');
        }
    }

    public function serialize(mixed $value): string
    {
        return $value;
    }

    public function deserialize(string $value): mixed
    {
        return $value;
    }

    public function validate(mixed $value): array
    {
        if (!is_string($value)) {
            return ['Value must be a string.'];
        }

        if (!array_key_exists($value, $this->options)) {
            return ['Value must be one of the predefined options.'];
        }

        return [];
    }

    /**
     * This method validates that the provided options array is in the correct format.
     * Each option should be a value/label pair where the label is a string.
     */
    protected function validateOptions(array $options): bool
    {
        if (empty($options)) {
            return false;
        }

        // Ensure the array is a collection of value/label pairs
        if (array_is_list($options)) {
            return false;
        }

        foreach ($options as $value => $label) {
            if (empty($value)) {
                return false;
            }

            if (!is_string($label) || empty($label)) {
                return false;
            }
        }

        return true;
    }
}
