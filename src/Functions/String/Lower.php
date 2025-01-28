<?php

namespace FQL\Functions\String;

use FQL\Enum\Type;
use FQL\Functions\Core\SingleFieldFunction;

final class Lower extends SingleFieldFunction
{
    /**
     * @inheritDoc
     * @return string
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field;
        if (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }

        return mb_strtolower($value);
    }
}
