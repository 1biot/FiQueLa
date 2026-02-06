<?php

namespace FQL\Functions\Math;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\MultipleFieldsFunction;

final class Add extends MultipleFieldsFunction
{
    public function __invoke(array $item, array $resultItem): mixed
    {
        $sum = 0;
        foreach ($this->fields as $field) {
            $value = $this->getFieldValue($field, $item, $resultItem) ?? $field;
            if (is_string($value)) {
                $value = Type::matchByString($value);
            }

            // Treat empty/null as 0 for additive operations
            if ($value === null || $value === '') {
                $value = 0;
            }

            if (!is_numeric($value) && is_string($value)) {
                throw new UnexpectedValueException(sprintf('Field "%s" value is not numeric: %s', $field, $value));
            }

            $sum += $value;
        }
        return $sum;
    }
}
