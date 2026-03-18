<?php

namespace FQL\Functions\Utils;

use FQL\Functions\Core\SingleFieldFunction;

class ArraySearch extends SingleFieldFunction
{
    public function __construct(string $field, private string $value)
    {
        parent::__construct($field);
    }
    /**
     * @inheritDoc
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $haystack = $this->getFieldValue($this->field, $item, $resultItem);
        if (!is_array($haystack)) {
            return null;
        }

        return array_search($this->value, $haystack, true);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, "%s")',
            $this->getName(),
            $this->field,
            $this->value
        );
    }
}
