<?php

namespace FQL\Functions\Utils;

use FQL\Conditions;
use FQL\Enum;
use FQL\Functions;
use FQL\Sql;

class SelectCase extends Functions\Core\BaseFunction
{
    /**
     * @var array<int, array{condition: Conditions\SimpleCondition|null, statement: string}> $conditions
     */
    private array $conditions = [];

    public function addCondition(string $conditionString, string $statement): void
    {
        $fqlTokenizer = new Sql\SqlLexer();
        $fqlTokenizer->tokenize($conditionString);
        [$field, $operator, $value] = $fqlTokenizer->parseSingleCondition();
        $condition = new Conditions\SimpleCondition(
            Enum\LogicalOperator::AND,
            $field,
            $operator,
            $value
        );
        $this->conditions[] = [
            'condition' => $condition,
            'statement' => $statement,
        ];
    }

    public function addDefault(string $statement): void
    {
        $this->conditions[] = [
            'condition' => null,
            'statement' => $statement,
        ];
    }

    public function hasDefaultStatement(): bool
    {
        return count(array_filter($this->conditions, fn ($condition) => $condition['condition'] === null)) > 0;
    }

    public function hasConditions(): bool
    {
        return count(array_filter($this->conditions, fn ($condition) => $condition['condition'] !== null)) > 0;
    }

    public function __invoke(array $item, array $resultItem): mixed
    {
        $result = null;
        foreach ($this->conditions as $condition) {
            if ($condition['condition'] === null) {
                $result = $this->getFieldValue($condition['statement'], $item, $resultItem)
                    ?? ($this->isQuoted($condition['statement'])
                        ? $this->removeQuotes($condition['statement'])
                        : Enum\Type::matchByString($condition['statement'])
                    );
                break;
            }
            if ($condition['condition']->evaluate(array_merge($item, $resultItem), true)) {
                $result = $this->getFieldValue($condition['statement'], $item, $resultItem)
                    ?? ($this->isQuoted($condition['statement'])
                        ? $this->removeQuotes($condition['statement'])
                        : Enum\Type::matchByString($condition['statement'])
                );
                break;
            }
        }
        return $result;
    }

    public function __toString()
    {
        $conditions = [];
        foreach ($this->conditions as $condition) {
            if ($condition['condition'] === null) {
                $conditions[] = sprintf('ELSE %s', $condition['statement']);
            } else {
                $conditions[] = sprintf(
                    'WHEN %s THEN %s',
                    $condition['condition']->render(),
                    $condition['statement']
                );
            }
        }
        return sprintf(
            'CASE %s END',
            implode(' ', $conditions)
        );
    }
}
