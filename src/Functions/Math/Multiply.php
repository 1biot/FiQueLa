<?php

namespace FQL\Functions\Math;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\MultipleFieldsFunction;

final class Multiply extends MultipleFieldsFunction
{
    public function __invoke(array $item, array $resultItem): mixed
    {
        $acc = null;
        foreach ($this->fields as $field) {
            $value = $this->getFieldValue($field, $item, $resultItem) ?? $field;
            if (is_string($value)) {
                $value = Type::matchByString($value);
            }

            if ($value === null || $value === '') {
                $value = 0;
            }

            if (!is_numeric($value) && is_string($value)) {
                throw new UnexpectedValueException(sprintf('Field "%s" value is not numeric: %s', $field, $value));
            }

            if ($acc === null) {
                $acc = $value;
            } else {
                $acc *= $value;
            }
        }
        return $acc ?? 0;
    }
}
