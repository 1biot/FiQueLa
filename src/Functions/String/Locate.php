<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\MultipleFieldsFunction;

class Locate extends MultipleFieldsFunction
{
    public function __construct(private string $substring, private string $field, private ?int $position = null)
    {
        parent::__construct($substring, $field, (string) $position);
    }

    /**
     * @inheritDoc
     */
    public function __invoke(array $item, array $resultItem): mixed
    {
        $haystack = $this->getFieldValue($this->field, $item, $resultItem) ?? $this->field;
        $needle = $this->getFieldValue($this->substring, $item, $resultItem) ?? $this->substring;

        // Only scalar or null values are allowed
        if ((!is_scalar($haystack) && $haystack !== null) || (!is_scalar($needle) && $needle !== null)) {
            return null;
        }

        // Ensure both are strings
        $haystack = (string) $haystack;
        $needle = (string) $needle;

        // MySQL uses 1-based indexing
        $offset = max(1, $this->position ?? 1) - 1;

        // Search within the haystack starting at offset
        $found = mb_strpos(mb_substr($haystack, $offset), $needle);

        // Return 0 if not found, otherwise actual position (1-based)
        return $found === false ? 0 : $found + $offset + 1;
    }

    public function __toString(): string
    {
        $positionPart = $this->position !== null ? ", {$this->position}" : '';
        return sprintf(
            '%s(%s, %s%s)',
            $this->getName(),
            $this->substring,
            $this->field,
            $positionPart
        );
    }
}
