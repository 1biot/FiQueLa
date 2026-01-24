<?php

namespace FQL\Functions\Aggregate;

use FQL\Enum\Type;
use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\SingleFieldAggregateFunction;
use FQL\Interface\Query;

class GroupConcat extends SingleFieldAggregateFunction
{
    public function __construct(
        string $field,
        private readonly string $separator = ',',
        bool $distinct = false
    ) {
        parent::__construct($field, $distinct);
    }

    public function __invoke(array $items): mixed
    {
        $seen = $this->distinct ? $this->resetDistinctSeen() : [];
        $values = [];

        foreach ($items as $item) {
            $value = $this->getFieldValue($this->field, $item, false);
            if (is_string($value)) {
                $value = Type::matchByString($value);
            }

            if ($value === null) {
                continue;
            }

            if (!$this->isDistinctValue($value, $seen)) {
                continue;
            }

            $values[] = $value;
        }

        return implode(
            $this->separator,
            $values
        );
    }

    public function initAccumulator(): mixed
    {
        $accumulator = [
            'value' => '',
            'hasValue' => false,
        ];

        if (!$this->distinct) {
            return $accumulator;
        }

        $accumulator['seen'] = [];
        return $accumulator;
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

        if ($this->distinct) {
            if (!$this->isDistinctValue($value, $accumulator['seen'])) {
                return $accumulator;
            }
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
        $distinct = $this->distinct ? Query::DISTINCT . ' ' : '';
        return sprintf(
            '%s(%s%s, "%s")',
            $this->getName(),
            $distinct,
            $this->field,
            $this->separator
        );
    }
}
