<?php

namespace UQL\Functions;

use UQL\Enum\Type;
use UQL\Exceptions\InvalidArgumentException;

final class Mod extends SingleFieldFunction
{
    public function __construct(string $field, private readonly int $divisor)
    {
        if ($this->divisor === 0) {
            throw new InvalidArgumentException('Divisor cannot be zero');
        }

        parent::__construct($field);
    }

    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem);
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

        return $value % $this->divisor;
    }
}
