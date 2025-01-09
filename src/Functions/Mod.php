<?php

namespace UQL\Functions;

use UQL\Enum\Type;
use UQL\Exceptions\UnexpectedValueException;

final class Mod extends SingleFieldFunction
{
    public function __construct(string $field, private readonly int $divisor)
    {
        if ($this->divisor === 0) {
            throw new UnexpectedValueException('Divisor cannot be zero');
        }

        parent::__construct($field);
    }

    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem);
        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if (!is_numeric($value) && is_string($value)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Field "%s" value is not numeric: %s',
                    $this->field,
                    $value
                )
            );
        }

        return fmod($value, $this->divisor);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, %s)',
            $this->getName(),
            $this->field,
            $this->divisor
        );
    }
}
