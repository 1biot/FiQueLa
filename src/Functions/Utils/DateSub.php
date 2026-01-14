<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\MultipleFieldsFunction;

class DateSub extends MultipleFieldsFunction
{
    public function __construct(private readonly string $dateField, private readonly string $interval)
    {
        parent::__construct($dateField, $interval);
    }

    public function __invoke(array $item, array $resultItem): ?string
    {
        $dateValue = $this->getFieldValue($this->dateField, $item, $resultItem) ?? $this->dateField;
        $intervalValue = $this->getFieldValue($this->interval, $item, $resultItem) ?? $this->interval;

        if (!is_string($dateValue) || !is_string($intervalValue) || strtotime($dateValue) === false) {
            return null;
        }

        $intervalValue = trim($intervalValue);
        if ($intervalValue === '') {
            return null;
        }

        if (str_starts_with($intervalValue, '+')) {
            $intervalValue = '-' . ltrim(substr($intervalValue, 1));
        } elseif (!str_starts_with($intervalValue, '-')) {
            $intervalValue = '-' . $intervalValue;
        }

        try {
            $date = new \DateTimeImmutable($dateValue);
        } catch (\Exception) {
            return null;
        }

        $modified = $date->modify($intervalValue);
        if (!$modified instanceof \DateTimeImmutable) {
            return null;
        }

        return $modified->format('Y-m-d H:i:s');
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, "%s")',
            $this->getName(),
            $this->dateField,
            $this->interval
        );
    }
}
