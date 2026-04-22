<?php

namespace FQL\Sql\Lint;

/**
 * Generic recursive AST walker that yields every descendant node of a given
 * class/interface. Relies on the fact that FiQueLa AST nodes are plain
 * readonly VOs — all children live in public properties (object or array of
 * objects). Zero visitor boilerplate per rule.
 */
final class AstWalker
{
    /**
     * @template T of object
     * @param class-string<T> $type
     * @return \Generator<int, T>
     */
    public static function findAll(object $node, string $type): \Generator
    {
        if ($node instanceof $type) {
            yield $node;
        }
        foreach (get_object_vars($node) as $value) {
            yield from self::walkValue($value, $type);
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return \Generator<int, T>
     */
    private static function walkValue(mixed $value, string $type): \Generator
    {
        if (is_object($value)) {
            // Traverse only FQL AST objects — skip enums, DateTime, etc.
            if (str_starts_with($value::class, 'FQL\\Sql\\Ast\\')) {
                yield from self::findAll($value, $type);
            }
            return;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                yield from self::walkValue($item, $type);
            }
        }
    }
}
