<?php

namespace UQL\Functions\String;

use UQL\Enum\Type;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Functions\Core\SingleFieldFunction;

class Base64Encode extends SingleFieldFunction
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem);
        if ($value === null) {
            return null;
        }

        return base64_encode(Type::castValue($value, Type::STRING));
    }

    public function getName(): string
    {
        return 'TO_BASE64';
    }
}
