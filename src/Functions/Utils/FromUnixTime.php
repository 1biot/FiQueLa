<?php

namespace FQL\Functions\Utils;

use FQL\Functions;

class FromUnixTime extends Functions\Core\SingleFieldFunction
{
    public function __construct(string $field, private readonly string $format = 'c')
    {
        parent::__construct($field);
    }

    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field;
        if (!is_numeric($value)) {
            return null;
        }

        $dateTime = (new \DateTimeImmutable())->setTimestamp((int) $value);
        return $dateTime->format($this->format);
    }

    public function __toString(): string
    {
        return sprintf(
            'FROM_UNIXTIME(%s, "%s")',
            $this->field,
            $this->format
        );
    }
}
