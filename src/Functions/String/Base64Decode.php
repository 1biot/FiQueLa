<?php

namespace UQL\Functions\String;

use UQL\Enum\Type;
use UQL\Exceptions\InvalidArgumentException;
use UQL\Exceptions\UnexpectedValueException;
use UQL\Functions\Core\SingleFieldFunction;

class Base64Decode extends SingleFieldFunction
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

        return base64_decode(Type::castValue($value, Type::STRING));
    }

    public function getName(): string
    {
        return 'FROM_BASE64';
    }
}
