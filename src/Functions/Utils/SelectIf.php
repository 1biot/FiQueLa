<?php

namespace FQL\Functions\Utils;

use FQL\Conditions\IfStatementConditionGroup;
use FQL\Enum;
use FQL\Functions\Core\MultipleFieldsFunction;
use FQL\Sql;

class SelectIf extends MultipleFieldsFunction
{
    private IfStatementConditionGroup $conditionGroup;

    public function __construct(
        string $conditionString,
        protected string $trueStatement,
        protected string $falseStatement
    ) {
        $fqlTokenizer = new Sql\SqlLexer();
        $fqlTokenizer->tokenize($conditionString);
        $this->conditionGroup = $fqlTokenizer->parseConditionGroup(new IfStatementConditionGroup());

        parent::__construct($conditionString, $trueStatement, $falseStatement);
    }

    public function __invoke(array $item, array $resultItem): mixed
    {
        if ($this->conditionGroup->evaluate(array_merge($item, $resultItem), true)) {
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
