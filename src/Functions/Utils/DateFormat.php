<?php

namespace FQL\Functions\Utils;

use FQL\Functions;

class DateFormat extends Functions\Core\SingleFieldFunction
{
    public function __construct(string $field, private readonly string $format = 'c')
    {
        parent::__construct($field);
    }

    public function __invoke(array $item, array $resultItem): ?string
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field;
        if (is_string($value) && strtotime($value) !== false) {
            try {
                $value = new \DateTimeImmutable($value);
            } catch (\Exception) {
                $value = null;
            }
        }

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
