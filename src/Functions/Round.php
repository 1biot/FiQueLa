<?php

namespace UQL\Functions;

use UQL\Enum\Type;
use UQL\Exceptions\InvalidArgumentException;

final class Round extends SingleFieldFunction
{
    public function __construct(string $field, private readonly int $precision = 0)
    {
        parent::__construct($field);
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     * @return float|int
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? '';
        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Field "%s" value is not numeric: %s',
                    $this->field,
                    $value
                )
            );
        }

        return round($value, $this->precision);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, %d)',
            $this->getName(),
            $this->field,
            $this->precision
        );
    }
}
