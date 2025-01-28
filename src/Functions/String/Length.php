<?php

namespace FQL\Functions\String;

use FQL\Enum\Type;
use FQL\Functions\Core\SingleFieldFunction;

final class Length extends SingleFieldFunction
{
    /**
     * @inheritDoc
     * @return int
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field;
        if (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }

        return mb_strlen($value);
    }
}
