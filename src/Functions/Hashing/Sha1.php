<?php

namespace FQL\Functions\Hashing;

use FQL\Enum\Type;
use FQL\Functions\Core\SingleFieldFunction;

final class Sha1 extends SingleFieldFunction
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

        return sha1($value);
    }
}
