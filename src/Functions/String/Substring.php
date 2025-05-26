<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\MultipleFieldsFunction;

class Substring extends MultipleFieldsFunction
{
    public function __construct(private string $field, private int $start, private ?int $length = null)
    {
        parent::__construct($field, (string) $start, (string) $length);
    }

    /**
     * @inheritDoc
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem);
        if (!(is_scalar($value) || $value === null)) {
            return null;
        }

        return mb_substr((string) $value, $this->start, $this->length);
    }

    public function __toString(): string
    {
        $lengthPart = $this->length !== null ? ", {$this->length}" : '';
        return sprintf(
            '%s(%s, %d%s)',
            $this->getName(),
            $this->field,
            $this->start,
            $lengthPart
        );
    }
}
