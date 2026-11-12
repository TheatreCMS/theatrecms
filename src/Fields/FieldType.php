<?php

namespace TheatreCMS\Fields;

interface FieldType
{
    public const string TYPE = 'field';

    public function serialize(mixed $value): string;
    public function deserialize(string $value): mixed;

    /**
     * Empty array means the value is valid; otherwise, it contains ALL error messages.
     *
     * @return list<string>
     */
    public function validate(mixed $value): array;
}
