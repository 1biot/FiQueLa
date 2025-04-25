<?php

namespace FQL\Functions\Utils;

use FQL\Enum\Type;
use FQL\Functions\Core\SingleFieldFunction;

final class Length extends SingleFieldFunction
{
    /**
     * @inheritDoc
     * @return int
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem)
            ?? ($this->isQuoted($this->field) ? $this->removeQuotes($this->field) : null);
        if ($value === null) {
            return 0;
        } elseif (is_array($value)) {
            return count($value);
        } elseif (!is_string($value)) {
            $value = Type::castValue($value, Type::STRING);
        }

        return mb_strlen($value);
    }
}
