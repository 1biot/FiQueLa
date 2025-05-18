<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\SingleFieldFunction;

class ArrayFilter extends SingleFieldFunction
{
    public function __invoke(array $item, array $resultItem): mixed
    {
        $array = $this->getFieldValue($this->field, $item, $resultItem);
        if (!is_array($array)) {
            return null;
        }

        return array_values(array_filter($array));
    }
}
