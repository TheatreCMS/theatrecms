<?php

namespace TheatreCMS\Fields\Types;

use TheatreCMS\Fields\FieldType;

class Text implements FieldType
{
    public const string TYPE = 'text';

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
        if (is_string($value)) {
            return [];
        }

        return ['Value must be a string.'];
    }
}
