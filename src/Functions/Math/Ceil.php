<?php

namespace FQL\Functions\Math;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\SingleFieldFunction;

final class Ceil extends SingleFieldFunction
{
    /**
     * @inheritDoc
     * @return float
     * @throws UnexpectedValueException
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? '';
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

        return ceil($value);
    }
}
