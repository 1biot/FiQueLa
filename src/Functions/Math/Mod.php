<?php

namespace UQL\Functions\Math;

use UQL\Enum\Type;
use UQL\Exceptions\UnexpectedValueException;
use UQL\Functions\Core\SingleFieldFunction;

final class Mod extends SingleFieldFunction
{
    /**
     * @throws UnexpectedValueException
     */
    public function __construct(string $field, private readonly int $divisor)
    {
        parent::__construct($field);
        if ($this->divisor === 0) {
            throw new UnexpectedValueException(sprintf('%s: Divisor cannot be zero', $this));
        }
    }

    /**
     * @throws UnexpectedValueException
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem);
        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if (!is_numeric($value) && is_string($value)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Field "%s" value is not numeric: %s',
                    $this->field,
                    $value
                )
            );
        }

        return fmod($value, $this->divisor);
    }

    /**
     * @throws UnexpectedValueException
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(%s, %s)',
            $this->getName(),
            $this->field,
            $this->divisor
        );
    }
}
