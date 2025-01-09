<?php

namespace UQL\Functions;

use UQL\Enum\Type;

class GroupConcat extends SingleFieldAggregateFunction
{
    public function __construct(string $field, private readonly string $separator = ',')
    {
        parent::__construct($field);
    }

    public function __invoke(array $items): mixed
    {
        return implode(
            $this->separator,
            array_map(function ($item) {
                $value = $this->getFieldValue($this->field, $item);
                if (is_string($value)) {
                    $value = Type::matchByString($value);
                }

                return $value;
            }, $items)
        );
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, "%s")',
            $this->getName(),
            $this->field,
            $this->separator
        );
    }
}
