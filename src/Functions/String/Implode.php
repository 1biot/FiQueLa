<?php

namespace UQL\Functions\String;

use UQL\Exceptions\UnexpectedValueException;
use UQL\Functions\Core\SingleFieldFunction;

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
        $value = $this->getFieldValue($this->field, $item, $resultItem) ?? '';
        if (!is_array($value)) {
            throw new UnexpectedValueException(sprintf('Field "%s" is not an array', $this->field));
        }

        return implode($this->separator, $value);
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
            $this->field
        );
    }
}
