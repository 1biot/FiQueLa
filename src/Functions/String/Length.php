<?php

namespace UQL\Functions\String;

use UQL\Enum\Type;
use UQL\Functions\Core\SingleFieldFunction;

final class Length extends SingleFieldFunction
{
    /**
     * @inheritDoc
     * @return int
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? '';
        if (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }

        return mb_strlen($value);
    }
}
