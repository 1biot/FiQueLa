<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\MultipleFieldsFunction;

class LeftPad extends MultipleFieldsFunction
{
    public function __construct(
        private readonly string $field,
        private readonly int $length,
        private readonly string $padString = " "
    ) {
        parent::__construct($this->field, (string) $this->length, $this->padString);
    }

    public function __invoke(array $item, array $resultItem): ?string
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? '';
        if (is_scalar($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        return str_pad($value, $this->length, $this->padString, STR_PAD_LEFT);
    }

    public function __toString(): string
    {
        return sprintf(
            'LPAD(%s, %d, "%s")',
            $this->field,
            $this->length,
            $this->padString
        );
    }
}
