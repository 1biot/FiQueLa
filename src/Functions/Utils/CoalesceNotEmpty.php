<?php

namespace UQL\Functions\Utils;

final class CoalesceNotEmpty extends Coalesce
{
    /**
     * @inheritDoc
     * @return string
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        foreach ($this->fields as $field) {
            $field = trim($field);
            $value = $this->getFieldValue($field, $item, $resultItem) ?? $field;
            if (!empty($value)) {
                return $value;
            }
        }

        return '';
    }

    public function __toString(): string
    {
        return sprintf(
            'COALESCE_NE(%s)',
            implode(', ', $this->fields)
        );
    }
}
