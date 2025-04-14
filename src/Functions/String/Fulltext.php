<?php

namespace FQL\Functions\String;

use FQL\Enum;
use FQL\Exception;
use FQL\Functions;
use FQL\Traits;

final class Fulltext extends Functions\Core\MultipleFieldsFunction
{
    use Traits\Helpers\StringOperations;

    /** @param string[] $fields */
    public function __construct(
        array $fields,
        private ?string $query = null,
        private Enum\Fulltext $mode = Enum\Fulltext::NATURAL
    ) {
        parent::__construct(...$fields);
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    public function setMode(Enum\Fulltext $mode): void
    {
        $this->mode = $mode;
    }

    public function __invoke(array $item, array $resultItem): float|int
    {
        if ($this->query === null) {
            throw new Exception\QueryLogicException('Against query is not set');
        }

        $score = 0;
        $terms = $this->splitQuery($this->query);
        foreach ($this->fields as $index => $field) {
            $field = trim($field);
            $fieldValue = $this->getFieldValue($field, $item, $resultItem) ?? '';
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
