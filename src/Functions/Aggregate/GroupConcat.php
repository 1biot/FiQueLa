<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\SingleFieldAggregateFunction;

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
                    $value = $this->getFieldValue($this->field, $item, false);
                    if (is_string($value)) {
                        $value = Type::matchByString($value);
                    }

                    return $value;
                }, $items),
                fn($value) => $value !== null
            )
        );
    }

    public function initAccumulator(): mixed
    {
        return [
            'value' => '',
            'hasValue' => false,
        ];
    }

    /**
     * @inheritDoc
     */
    public function accumulate(mixed $accumulator, array $item): mixed
    {
        $value = $this->getFieldValue($this->field, $item, false);
        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if ($value === null) {
            return $accumulator;
        }

        $stringValue = (string) $value;
        if ($accumulator['hasValue']) {
            $accumulator['value'] .= $this->separator . $stringValue;
            return $accumulator;
        }

        $accumulator['value'] = $stringValue;
        $accumulator['hasValue'] = true;
        return $accumulator;
    }

    public function finalize(mixed $accumulator): mixed
    {
        return $accumulator['value'];
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
