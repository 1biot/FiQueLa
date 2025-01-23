<?php

namespace FQL\Functions\String;

use FQL\Enum;
use FQL\Functions;
use FQL\Traits;

final class Fulltext extends Functions\Core\MultipleFieldsFunction
{
    use Traits\Helpers\StringOperations;

    /** @param string[] $fields */
    public function __construct(
        array $fields,
        private readonly string $query,
        private readonly Enum\Fulltext $mode = Enum\Fulltext::NATURAL
    ) {
        parent::__construct(...$fields);
    }

    public function __invoke(array $item, array $resultItem): float|int
    {
        $score = 0;
        $terms = $this->splitQuery($this->query);
        foreach ($this->fields as $index => $field) {
            $field = trim($field);
            $fieldValue = $this->getFieldValue($field, $item, $resultItem);
            $weight = 1 / ($index + 1); // Weight by field order (lower index = higher weight).
            $score += $this->mode->calculate($this->extractPlainText($fieldValue), $terms) * $weight;
        }

        return $score;
    }

    /**
     * @param string $query
     * @return array<int, string>
     */
    private function splitQuery(string $query): array
    {
        $splitQuery = preg_split('/\s+/', $query, flags: PREG_SPLIT_NO_EMPTY);
        return $splitQuery === false ? [] : $splitQuery;
    }

    public function __toString(): string
    {
        return sprintf(
            'MATCH(%s) AGAINST("%s" IN %s MODE)',
            implode(', ', $this->fields),
            $this->query,
            $this->mode->value,
        );
    }
}
