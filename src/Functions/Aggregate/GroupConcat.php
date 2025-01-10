<?php

namespace UQL\Functions\Aggregate;

use UQL\Enum\Type;
use UQL\Exceptions\UnexpectedValueException;
use UQL\Functions\Core\SingleFieldAggregateFunction;

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
            array_filter(
                array_map(function ($item) {
                    $value = $this->getFieldValue($this->field, $item);
                    if (is_string($value)) {
                        $value = Type::matchByString($value);
                    }

                    return $value;
                }, $items),
                fn($value) => $value !== null
            )
        );
    }

    /**
     * @throws UnexpectedValueException
     */
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
