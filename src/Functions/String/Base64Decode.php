<?php

namespace FQL\Functions\String;

use FQL\Enum\Type;
use FQL\Exception\InvalidArgumentException;
use FQL\Functions\Core\SingleFieldFunction;

class Base64Decode extends SingleFieldFunction
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        return base64_decode(
            Type::castValue(
                $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field,
                Type::STRING
            )
        );
    }
}
