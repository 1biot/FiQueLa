<?php

namespace FQL\Functions\Utils;

use FQL\Conditions\SimpleCondition;
use FQL\Enum;
use FQL\Exception;
use FQL\Functions\Core\MultipleFieldsFunction;
use FQL\Sql;

class SelectIf extends MultipleFieldsFunction
{
    private SimpleCondition $condition;

    public function __construct(
        string $conditionString,
        protected string $trueStatement,
        protected string $falseStatement
    ) {
        $fqlTokenizer = new Sql\SqlLexer();
        $fqlTokenizer->tokenize($conditionString);
        [$field, $operator, $value] = $fqlTokenizer->parseSingleCondition();
        $this->condition = new SimpleCondition(
            Enum\LogicalOperator::AND,
            $field,
            $operator,
            $value
        );

        parent::__construct($conditionString, $trueStatement, $falseStatement);
    }

    public function __invoke(array $item, array $resultItem): mixed
    {
        if ($this->condition->evaluate(array_merge($item, $resultItem), true)) {
            $trueStatement = $this->getFieldValue($this->trueStatement, $item, $resultItem) ?? $this->trueStatement;
            if (is_string($trueStatement)) {
                $trueStatement = Enum\Type::matchByString($trueStatement);
            }
            return $trueStatement;
        } else {
            $falseStatement = $this->getFieldValue($this->falseStatement, $item, $resultItem) ?? $this->falseStatement;
            if (is_string($falseStatement)) {
                $falseStatement = Enum\Type::matchByString($falseStatement);
            }
            return $falseStatement;
        }
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s, %s, %s)',
            'IF',
            $this->fields[0],
            $this->trueStatement,
            $this->falseStatement,
        );
    }
}
