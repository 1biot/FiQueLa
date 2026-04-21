<?php

namespace FQL\Sql\Support;

use FQL\Conditions\BaseConditionGroup;
use FQL\Conditions\GroupCondition;
use FQL\Conditions\SimpleCondition;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Builder\ExpressionCompiler;
use FQL\Sql\Parser\ConditionGroupParser;
use FQL\Sql\Parser\ConditionParser;
use FQL\Sql\Parser\ExpressionParser;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;

/**
 * Parses an FQL condition fragment (e.g. `a > 1 AND b IS NULL`) into a populated
 * `BaseConditionGroup` runtime tree.
 *
 * Replaces the legacy `SqlLexer::parseConditionGroup()` used by `Functions\Utils\SelectIf`
 * and `Functions\Utils\SelectCase`. Those classes receive a condition string from the
 * fluent API and need to build an evaluatable condition group; the full parser pipeline
 * produces a `ConditionGroupNode` which is then flattened into the target group type
 * (WhereConditionGroup, IfStatementConditionGroup, CaseStatementConditionGroup, ...).
 */
final class ConditionStringParser
{
    /**
     * Populates `$target` with conditions parsed from `$conditionString`.
     *
     * @template T of BaseConditionGroup
     * @param T $target
     * @return T
     */
    public static function populate(string $conditionString, BaseConditionGroup $target): BaseConditionGroup
    {
        $group = self::parseToNode($conditionString);
        self::flattenInto($target, $group);
        return $target;
    }

    private static function parseToNode(string $conditionString): ConditionGroupNode
    {
        $tokens = (new Tokenizer())->tokenize($conditionString);
        $stream = new TokenStream($tokens);

        $expressionParser = new ExpressionParser();
        $conditionParser = new ConditionParser($expressionParser);
        $groupParser = new ConditionGroupParser($conditionParser);
        $expressionParser->setConditionGroupParser($groupParser);

        return $groupParser->parseGroup($stream);
    }

    private static function flattenInto(GroupCondition $target, ConditionGroupNode $astGroup): void
    {
        $compiler = new ExpressionCompiler();
        foreach ($astGroup->entries as $entry) {
            $logical = $entry['logical'];
            $condition = $entry['condition'];
            if ($condition instanceof ConditionGroupNode) {
                $nested = new GroupCondition($logical, $target);
                $target->addCondition($logical, $nested);
                self::flattenInto($nested, $condition);
                continue;
            }
            $field = self::fieldString($compiler, $condition->left);
            $value = $compiler->scalarRightValue($condition->right, $condition->operator);
            $target->addCondition(
                $logical,
                new SimpleCondition($logical, $field, $condition->operator, $value)
            );
        }
    }

    private static function fieldString(
        ExpressionCompiler $compiler,
        \FQL\Sql\Ast\Expression\ExpressionNode $node
    ): string {
        if ($node instanceof \FQL\Sql\Ast\Expression\ColumnReferenceNode) {
            return $node->name;
        }
        if ($node instanceof \FQL\Sql\Ast\Expression\LiteralNode) {
            return $compiler->renderLiteral($node);
        }
        return $compiler->renderExpression($node);
    }
}
