<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\SingleFieldFunction;

class Year extends SingleFieldFunction
{
    public function __invoke(array $item, array $resultItem): ?int
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field;
        $date = $this->parseDateValue($value);
        if ($date === null) {
            return null;
        }

        return (int) $date->format('Y');
    }

    private function parseDateValue(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value)) {
            return (new \DateTimeImmutable())->setTimestamp($value);
        }

        if (is_string($value) && strtotime($value) !== false) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
