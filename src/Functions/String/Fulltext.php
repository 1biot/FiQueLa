<?php

namespace FQL\Functions\String;

use FQL\Enum;
use FQL\Functions\Core\ScalarFunction;
use FQL\Traits;

final class Fulltext implements ScalarFunction
{
    use Traits\Helpers\StringOperations;

    public static function name(): string
    {
        return 'MATCH';
    }

    /**
     * @param array<int, mixed> $fieldValues
     */
    public static function execute(array $fieldValues, string $query, Enum\Fulltext $mode = Enum\Fulltext::NATURAL): float
    {
        $score = 0;
        $terms = self::splitQuery($query);
        $stringHelper = new self();
        foreach (array_values($fieldValues) as $index => $fieldValue) {
            $weight = 1 / ($index + 1); // Weight by field order (lower index = higher weight).
            $score += $mode->calculate($stringHelper->extractPlainText((string) $fieldValue), $terms) * $weight;
        }

        return $score;
    }

    /**
     * @param string $query
     * @return array<int, string>
     */
    private static function splitQuery(string $query): array
    {
        $splitQuery = preg_split('/\s+/', $query, flags: PREG_SPLIT_NO_EMPTY);
        return $splitQuery === false ? [] : $splitQuery;
    }
}
