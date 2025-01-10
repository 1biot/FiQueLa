<?php

namespace UQL\Functions\Hashing;

use UQL\Enum\Type;
use UQL\Functions\Core\SingleFieldFunction;

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
