<?php

namespace UQL\Functions\String;

use UQL\Exceptions\UnexpectedValueException;
use UQL\Functions\Core\MultipleFieldsFunction;

class ConcatWS extends MultipleFieldsFunction
{
    public function __construct(private readonly string $separator, string ...$fields)
    {
        parent::__construct(...$fields);
    }
    /**
     * @inheritDoc
     * @return string
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $result = [];
        foreach ($this->fields as $field) {
            $field = trim($field);
            $result[] = $this->getFieldValue($field, $item, $resultItem) ?? $field;
        }
        return implode($this->separator, $result);
    }

    /**
     * @throws UnexpectedValueException
     */
    public function __toString(): string
    {
        return sprintf(
            '%s("%s", %s)',
            $this->getName(),
            $this->separator,
            implode(', ', $this->fields)
        );
    }
}
