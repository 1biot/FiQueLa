<?php

namespace FQL\Functions\String;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\SingleFieldFunction;

class Implode extends SingleFieldFunction
{
    public function __construct(string $field, private readonly string $separator = ',')
    {
        parent::__construct($field);
    }

    /**
     * @inheritDoc
     * @throws UnexpectedValueException
     * @return string
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field;
        if (!is_array($value) && !is_scalar($value)) {
            throw new UnexpectedValueException(sprintf('Field "%s" is not an array', $this->field));
        }

        return is_scalar($value) ? (string) $value : implode($this->separator, $value);
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
