<?php

namespace UQL\Functions;

use UQL\Enum\Type;

final class Reverse extends SingleFieldFunction
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
