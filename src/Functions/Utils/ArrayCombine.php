<?php

namespace FQL\Functions\Utils;

use FQL\Functions;
use FQL\Traits;

class ArrayCombine extends Functions\Core\MultipleFieldsFunction
{
    use Traits\Helpers\StringOperations;

    public function __construct(private string $keysArrayField, private string $valueArrayField)
    {
        parent::__construct($keysArrayField, $valueArrayField);
    }

    /**
     * @inheritDoc
     * @return array<int|string, mixed>|null
     */
    public function __invoke(array $item, array $resultItem): ?array
    {
        $keys = $this->getFieldValue($this->keysArrayField, $item, $resultItem);
        $values = $this->getFieldValue($this->valueArrayField, $item, $resultItem);

        if (
            !is_array($keys)
            || !is_array($values)
        ) {
            return null;
        }

        if ($this->isAssoc($keys)) {
            $keys = array_values($keys);
        }

        if ($this->isAssoc($values)) {
            $values = array_values($values);
        }

        return array_combine($keys, $values);
    }
}
