<?php

namespace SQL\Printer;

use FQL\Enum\LogicalOperator;
use FQL\Enum\Sort;
use FQL\Query\FileQuery;
use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionExpressionNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Ast\Node\GroupByClauseNode;
use FQL\Sql\Ast\Node\OrderByClauseNode;
use FQL\Sql\Ast\Node\OrderByItemNode;
use FQL\Sql\Ast\Node\SelectFieldNode;
use FQL\Sql\Ast\Node\SelectStatementNode;

/**
 * Test helpers for printer tests: a structural view of an AST that ignores what a
 * printer may legitimately spell differently (positions, spans, the raw spelling of
 * literals and file queries, backticks around column path segments, the unused logical
 * operator of the first condition of a group), and a deep copy that replaces every node
 * with a new object.
 */
final class AstShape
{
    public static function of(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::of($item), $value);
        }
        if ($value instanceof \BackedEnum) {
            return $value::class . '::' . $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value::class . '::' . $value->name;
        }
        if ($value instanceof FileQuery) {
            return 'FileQuery(' . $value . ')';
        }
        if (!$value instanceof AstNode) {
            return $value;
        }
        $shape = ['@' => substr(strrchr($value::class, '\\') ?: $value::class, 1)];
        foreach (get_object_vars($value) as $name => $property) {
            if ($name === 'position' || $name === 'span') {
                continue;
            }
            if (($value instanceof LiteralNode || $value instanceof FileQueryNode) && $name === 'raw') {
                continue;
            }
            if ($value instanceof ColumnReferenceNode && $name === 'quoted') {
                continue;
            }
            if ($value instanceof ColumnReferenceNode && $name === 'name') {
                $shape['path'] = self::path((string) $property);
                continue;
            }
            if ($value instanceof ConditionGroupNode && $name === 'entries') {
                $entries = [];
                foreach (array_values($property) as $i => $entry) {
                    $entries[] = [
                        'logical' => $i === 0 ? null : self::of($entry['logical']),
                        'condition' => self::of($entry['condition']),
                    ];
                }
                $shape['entries'] = $entries;
                continue;
            }
            $shape[$name] = self::of($property);
        }
        return $shape;
    }

    /**
     * A new object for every AST node (no spans), with the same content.
     */
    public static function deepCopy(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::deepCopy($item), $value);
        }
        if (!$value instanceof AstNode) {
            return $value;
        }
        $args = [];
        foreach (get_object_vars($value) as $name => $property) {
            $args[$name] = $name === 'span' ? null : self::deepCopy($property);
        }
        return new ($value::class)(...$args);
    }

    /**
     * Column path as the runtime accessor reads it: segments with backticks removed.
     */
    private static function path(string $name): string
    {
        preg_match_all('/`([^`]+)`|([^.`\[\]]+)|(\[])/', $name, $matches);
        $segments = [];
        foreach ($matches[0] as $part) {
            if ($part === '[]') {
                $segments[] = '[]';
                continue;
            }
            $segments[] = trim($part, '`');
        }
        return implode("\u{0}", $segments);
    }
}

/**
 * Random edits of a parsed AST for printer tests: removes, duplicates and reorders list
 * items (fields, conditions, arguments, IN values, ORDER BY / GROUP BY items, joins),
 * flips connectives and sort directions, adds and drops aliases and clauses. Untouched
 * subtrees keep their identity, like real edits. Uses `mt_rand()`; seed it first.
 */
final class RandomEdit
{
    public static function of(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::of($item), $value);
        }
        if (!$value instanceof AstNode) {
            return $value;
        }
        $changes = [];
        foreach (get_object_vars($value) as $name => $property) {
            if ($name === 'span' || $name === 'position') {
                continue;
            }
            $edited = self::of($property);
            if ($edited !== $property) {
                $changes[$name] = $edited;
            }
        }
        if (mt_rand(0, 3) === 0) {
            $changes = self::edit($value, $changes);
        }
        if ($changes === []) {
            return $value;
        }
        $args = get_object_vars($value);
        foreach ($changes as $name => $changed) {
            $args[$name] = $changed;
        }
        $args['span'] = null;
        return new ($value::class)(...$args);
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private static function edit(AstNode $node, array $changes): array
    {
        if ($node instanceof SelectStatementNode && $node->fields !== []) {
            $fields = self::shuffle($changes['fields'] ?? $node->fields, false);
            // Selected fields must stay before excluded ones (usort is stable).
            usort($fields, static fn (SelectFieldNode $a, SelectFieldNode $b): int => $a->excluded <=> $b->excluded);
            $changes['fields'] = $fields;
            if (mt_rand(0, 4) === 0) {
                $changes['where'] = null;
            }
            if (mt_rand(0, 4) === 0) {
                $changes['orderBy'] = null;
            }
            if (mt_rand(0, 4) === 0) {
                $changes['joins'] = self::shuffle($changes['joins'] ?? $node->joins, true);
            }
        } elseif ($node instanceof ConditionGroupNode && $node->entries !== []) {
            $entries = $changes['entries'] ?? $node->entries;
            $index = mt_rand(0, count($entries) - 1);
            $operation = mt_rand(0, 2);
            if ($operation === 0 && count($entries) > 1) {
                array_splice($entries, $index, 1);
            } elseif ($operation === 1) {
                $entries[] = ['logical' => self::logical(), 'condition' => AstShape::deepCopy($entries[$index]['condition'])];
            } else {
                $entries[$index]['logical'] = self::logical();
            }
            $changes['entries'] = array_values($entries);
        } elseif ($node instanceof OrderByClauseNode) {
            $changes['items'] = self::shuffle($changes['items'] ?? $node->items, false);
        } elseif ($node instanceof OrderByItemNode) {
            $changes['direction'] = $node->direction === Sort::ASC ? Sort::DESC : Sort::ASC;
        } elseif ($node instanceof GroupByClauseNode) {
            $changes['fields'] = self::shuffle($changes['fields'] ?? $node->fields, false);
        } elseif ($node instanceof FunctionCallNode && !in_array($node->name, ['IF', 'COLLECT_OBJECT'], true)) {
            $changes['arguments'] = self::shuffle($changes['arguments'] ?? $node->arguments, true);
        } elseif ($node instanceof ConditionExpressionNode && is_array($node->right) && str_ends_with($node->operator->value, 'IN')) {
            $changes['right'] = self::shuffle($changes['right'] ?? $node->right, true);
        } elseif ($node instanceof SelectFieldNode && !$node->excluded) {
            $changes['alias'] = $node->alias === null ? 'alias' . mt_rand(1, 9) : null;
        }
        return $changes;
    }

    /**
     * @param array<int, mixed> $list
     * @return list<mixed>
     */
    private static function shuffle(array $list, bool $mayBeEmpty): array
    {
        $list = array_values($list);
        $count = count($list);
        $operation = mt_rand(0, 3);
        if ($operation === 0 && ($count > 1 || ($mayBeEmpty && $count === 1))) {
            array_splice($list, mt_rand(0, $count - 1), 1);
        } elseif ($operation === 1 && $count > 0) {
            array_splice($list, mt_rand(0, $count), 0, [AstShape::deepCopy($list[mt_rand(0, $count - 1)])]);
        } elseif ($operation === 2 && $count > 1) {
            $list = array_reverse($list);
        } elseif ($operation === 3 && $count > 1) {
            $i = mt_rand(0, $count - 2);
            [$list[$i], $list[$i + 1]] = [$list[$i + 1], $list[$i]];
        }
        return array_values($list);
    }

    private static function logical(): LogicalOperator
    {
        return [LogicalOperator::AND, LogicalOperator::OR, LogicalOperator::XOR][mt_rand(0, 2)];
    }
}
