<?php

namespace UQL\Functions;

use UQL\Enum\Type;

final class Md5 extends SingleFieldFunction
{
    /**
     * @inheritDoc
     * @return string
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? '';
        if (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }

        return md5($value);
    }
}
