<?php

namespace UQL\Functions;

use UQL\Exceptions\InvalidArgumentException;

class Explode extends SingleFieldFunction
{
    public function __construct(string $field, private readonly string $separator = ',')
    {
        parent::__construct($field);
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     * @return string[]
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? null;
        if (!is_string($value) && $value !== null) {
            throw new InvalidArgumentException(sprintf('Field "%s" is not a string', $this->field));
        } elseif ($value === null) {
            return [];
        }

        return explode($this->separator, $value);
    }

    public function __toString()
    {
        return sprintf(
            '%s("%s", %s)',
            $this->getName(),
            $this->separator,
            $this->field
        );
    }
}
