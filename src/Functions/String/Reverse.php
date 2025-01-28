<?php

namespace FQL\Functions\String;

use FQL\Enum\Type;
use FQL\Exception\InvalidArgumentException;
use FQL\Functions\Core\SingleFieldFunction;

final class Reverse extends SingleFieldFunction
{
    /**
     * @inheritDoc
     * @return string
     * @throws InvalidArgumentException
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field;
        if (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }

        return $this->mbStrRev($value);
    }

    private function mbStrRev(string $string): string
    {
        $r = '';
        for ($i = mb_strlen($string); $i >= 0; $i--) {
            $r .= mb_substr($string, $i, 1);
        }
        return $r;
    }
}
