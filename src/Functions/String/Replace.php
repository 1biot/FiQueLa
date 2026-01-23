<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\SingleFieldFunction;

class Replace extends SingleFieldFunction
{
    public function __construct(
        string $field,
        private readonly string $fromString,
        private readonly string $newString
    ) {
        parent::__construct($field);
    }

    /**
     * @inheritDoc
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem);
        if (!is_scalar($value)) {
            return null;
        }

        return str_replace($this->fromString, $this->newString, (string) $value);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, "%s", "%s")',
            $this->getName(),
            $this->field,
            $this->fromString,
            $this->newString
        );
    }
}
