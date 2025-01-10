<?php

namespace UQL\Functions\Core;

use UQL\Exceptions\UnexpectedValueException;

abstract class MultipleFieldsFunction extends BaseFunction
{
    /** @var string[] $fields */
    protected readonly array $fields;

    public function __construct(string ...$fields)
    {
        $this->fields = $fields;
    }

    /**
     * @throws UnexpectedValueException
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(%s)',
            $this->getName(),
            implode(', ', $this->fields)
        );
    }
}
