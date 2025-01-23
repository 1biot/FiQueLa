<?php

namespace FQL\Enum;

enum Fulltext: string
{
    case NATURAL = 'NATURAL';
    case BOOLEAN = 'BOOLEAN';

    /**
     * @param string $fieldValue
     * @param string[] $terms
     * @return float
     */
    public function calculate(string $fieldValue, array $terms): float
    {
        return match ($this) {
            self::BOOLEAN => $this->calculateBooleanMatch($fieldValue, $terms),
            self::NATURAL => $this->calculateQueryMatch($fieldValue, $terms),
        };
    }

    /**
     * @param string $fieldValue
     * @param string[] $terms
     * @return float
     */
    private function calculateBooleanMatch(string $fieldValue, array $terms): float
    {
        $score = 0;
        $scoreArray = [
            '+' => [],
            '-' => [],
            '>' => [],
            '<' => [],
            '*' => [],
            '~' => [],
            'else' => [],
        ];
        foreach ($terms as $term) {
            $operator = $term[0];
            $word = ltrim($term, '+-<>*~');

            $contains = stripos($fieldValue, $word) !== false;

            if ($operator === '+') {
                $score += $contains ? 1 : 0;
                $scoreArray['+'][] = $word;
            } elseif ($operator === '-') {
                $score += !$contains ? 1 : 0;
                $scoreArray['-'][] = $word;
            } elseif ($operator === '>') {
                $score += $contains ? 2 : 0;
                $scoreArray['>'][] = $word;
            } elseif ($operator === '<') {
                $score += $contains ? 0.5 : 0;
                $scoreArray['<'][] = $word;
            } elseif ($operator === '*') {
                $score += $contains ? substr_count($fieldValue, $word) * 1.5 : 0; // weight for number of occurrences
                $scoreArray['*'][] = $word;
            } elseif ($operator === '~') {
                $score += $contains ? 0.7 : 0; // less weight
                $scoreArray['~'][] = $word;
            } else {
                $score += $contains ? 1 : 0;
                $scoreArray['else'][] = $word;
            }
        }

        return $score;
    }

    /**
     * @param string $fieldValue
     * @param string[] $terms
     * @return float
     */
    private function calculateQueryMatch(string $fieldValue, array $terms): float
    {
        $terms = array_map('strtolower', $terms);
        $score = 0;
        $scoreArray = [
            'presence' => [],
            'sequence_direct' => [],
            'sequence_indirect' => [],
        ];

        $fieldWords = explode(' ', $fieldValue);
        $queryLength = count($terms);
        for ($i = 0; $i < count($fieldWords); $i++) {
            if (!in_array($fieldWords[$i], $terms)) {
                continue;
            }

            $scoreArray['presence'][] = $fieldWords[$i];
            $score += 1;

            $sequenceMatch = true;
            for ($j = 0; $j < $queryLength; $j++) {
                if (
                    !isset($fieldWords[$i + $j]) ||
                    strtolower($fieldWords[$i + $j]) !== strtolower($terms[$j])
                ) {
                    $sequenceMatch = false;
                    break;
                }
            }

            if ($queryLength > 1 && $sequenceMatch) {
                $scoreArray['sequence_direct'][] = $fieldWords[$i];
                $score += $queryLength * 2; // Extra body za přímou posloupnost.
            } elseif ($queryLength > 1) {
                $scoreArray['sequence_indirect'][] = $fieldWords[$i];
                $score += $queryLength - 1; // Body za částečnou posloupnost.
            }
        }

        return $score;
    }
}
