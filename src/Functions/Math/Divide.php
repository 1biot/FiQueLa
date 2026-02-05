<?php

namespace FQL\Functions\Math;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\MultipleFieldsFunction;

final class Divide extends MultipleFieldsFunction
{
    public function __construct(string|float|int ...$fields)
    {
        parent::__construct(
            ...array_map(fn($field) => (is_float($field) || is_int($field)) ? sprintf('"%s"', $field) : $field, $fields)
        );
    }

    public function __invoke(array $item, array $resultItem): mixed
    {
        $acc = null;
        foreach ($this->fields as $field) {
            $value = $this->getFieldValue($field, $item, $resultItem);

            if ($value === null || $value === '') {
                $value = 0;
            }

            if (is_string($value)) {
                $value = Type::matchByString($value);
            }

            if (!is_numeric($value) && is_string($value)) {
                throw new UnexpectedValueException(sprintf('Field "%s" value is not numeric: %s', $field, $value));
            }

            if ($acc === null) {
                $acc = $value;
            } else {
                if ($value == 0) {
                    throw new UnexpectedValueException(sprintf('Division by zero using field "%s".', $field));
                }
                $acc = $acc / $value;
            }
        }
        return $acc ?? 0;
    }
}
