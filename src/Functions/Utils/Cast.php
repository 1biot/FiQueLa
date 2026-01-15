<?php

namespace FQL\Functions\Utils;

use FQL\Enum\Type;
use FQL\Functions\Core\BaseFunction;

class Cast extends BaseFunction
{
    public function __construct(private readonly string $field, private readonly Type $as)
    {
    }

    public function __invoke(array $item, array $resultItem): mixed
    {
        $value = $this->getFieldValue($this->field, $item, $resultItem);
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = Type::matchByString($value);
        }

        if ($this->as === Type::NUMBER) {
            return is_numeric($value) ? $value : 0;
        }

        return Type::castValue($value, $this->as === Type::TRUE || $this->as === Type::FALSE ? Type::BOOLEAN : $this->as);
    }


    public function __toString(): string
    {
        return sprintf(
            '%s(%s AS %s)',
            $this->getName(),
            $this->field,
            $this->getSqlType()
        );
    }

    private function getSqlType(): string
    {
        return match ($this->as) {
            Type::FLOAT => 'DOUBLE',
            Type::INTEGER => 'INT',
            Type::TRUE, Type::FALSE, Type::BOOLEAN => 'BOOLEAN',
            default => strtoupper($this->as->value),
        };
    }
}
