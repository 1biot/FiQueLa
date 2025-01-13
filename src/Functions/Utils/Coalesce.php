<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\MultipleFieldsFunction;

class Coalesce extends MultipleFieldsFunction
{
    /**
     * @inheritDoc
     * @return string
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        foreach ($this->fields as $field) {
            $field = trim($field);
            $value = $this->getFieldValue($field, $item, $resultItem);
            if ($value !== null) {
                return $value;
            }
        }

        return '';
    }
}
