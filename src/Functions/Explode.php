<?php

namespace UQL\Functions;

use UQL\Exceptions\UnexpectedValueException;

class Explode extends SingleFieldFunction
{
    public function __construct(string $field, private readonly string $separator = ',')
    {
        parent::__construct($field);
    }

    /**
     * @inheritDoc
     * @throws UnexpectedValueException
     * @return string[]
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? null;
        if (!is_string($value) && $value !== null) {
            throw new UnexpectedValueException(sprintf('Field "%s" is not a string', $this->field));
        } elseif ($value === null) {
            return [];
        } elseif ($this->separator === '') {
            return str_split($value);
        }

        return explode($this->separator, $value);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s("%s", %s)',
            $this->getName(),
            $this->separator,
            $this->field
        );
    }
}
