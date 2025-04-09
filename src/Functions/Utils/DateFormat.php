<?php

namespace FQL\Functions\Utils;

use FQL\Functions;

class DateFormat extends Functions\Core\MultipleFieldsFunction
{
    public function __construct(private readonly string $field, private readonly string $format = 'c')
    {
        parent::__construct($field, $format);
    }

    public function __invoke(array $item, array $resultItem): ?string
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field;
        if (!$value instanceof \DateTimeImmutable) {
            return null;
        }

        return $value->format($this->format);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, "%s")',
            $this->getName(),
            $this->field,
            $this->format
        );
    }
}
