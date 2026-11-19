<?php

namespace FQL\Sql\Printer\Internal;

use FQL\Enum\Type;
use FQL\Sql\Ast\Expression\LiteralNode;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Printer\KeywordCase;
use FQL\Sql\Printer\UnprintableNodeException;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenType;

/**
 * Writes identifiers and literals so that FiQueLa's tokenizer reads them back unchanged.
 * Every decision is checked against the {@see Tokenizer} itself instead of a copy of
 * its rules.
 *
 * @internal
 */
final class Lexical
{
    /** Plain (unquoted) path segment: what the tokenizer reads as one identifier word. */
    private const PLAIN_SEGMENT = '/^[A-Za-z_@$][A-Za-z0-9_@$\-]*$/';

    /**
     * An alias, CTE name or other single name (`AS alias`): bare when the tokenizer reads
     * it as one identifier, otherwise in backticks.
     */
    public static function identifier(string $name, ?object $node = null): string
    {
        if ($name === '') {
            throw new UnprintableNodeException('An empty identifier cannot be written', self::astNode($node));
        }
        $token = self::singleToken($name);
        if ($token !== null && $token->type === TokenType::IDENTIFIER && $token->value === $name) {
            return $name;
        }
        if (str_contains($name, '`')) {
            throw new UnprintableNodeException(
                sprintf('Identifier "%s" contains a backtick and cannot be written in FQL', $name),
                self::astNode($node)
            );
        }
        return '`' . $name . '`';
    }

    /**
     * A column reference: the printed text tokenizes to a single identifier whose value is
     * `$name` (or an equivalent backticked path when segments need quoting).
     */
    public static function columnReference(string $name, bool $quoted = false, ?object $node = null): string
    {
        if ($name === '') {
            throw new UnprintableNodeException('An empty column name cannot be written', self::astNode($node));
        }

        if (str_contains($name, '`')) {
            // Already written in path syntax with escaped segments (`a`.`b c`).
            $token = self::singleToken($name);
            if ($token !== null && $token->isAnyOf(TokenType::IDENTIFIER, TokenType::IDENTIFIER_QUOTED)) {
                return $name;
            }
            throw new UnprintableNodeException(
                sprintf('Column "%s" cannot be written in FQL', $name),
                self::astNode($node)
            );
        }

        if (!$quoted) {
            $token = self::singleToken($name);
            if ($token !== null && $token->type === TokenType::IDENTIFIER && $token->value === $name) {
                return $name;
            }
        } elseif (preg_match('/[.\[\]*]/', $name) !== 1) {
            // A quoted single segment (`my col`, `Kód`) stays quoted.
            return '`' . $name . '`';
        }

        // Quote segment by segment: `Kód`.items[].name
        $segments = explode('.', $name);
        $out = [];
        foreach ($segments as $index => $segment) {
            if ($segment === '*' && $index === count($segments) - 1 && $index > 0) {
                $out[] = '*';
                continue;
            }
            $markers = '';
            while (str_ends_with($segment, '[]')) {
                $markers .= '[]';
                $segment = substr($segment, 0, -2);
            }
            if ($segment === '' || str_contains($segment, '[') || str_contains($segment, ']')) {
                throw new UnprintableNodeException(
                    sprintf('Column "%s" cannot be written in FQL', $name),
                    self::astNode($node)
                );
            }
            $plain = preg_match(self::PLAIN_SEGMENT, $segment) === 1
                && ($index > 0 || !self::isReservedWord($segment));
            $out[] = ($plain ? $segment : '`' . $segment . '`') . $markers;
        }
        $text = implode('.', $out);

        $token = self::singleToken($text);
        if ($token === null || !$token->isAnyOf(TokenType::IDENTIFIER, TokenType::IDENTIFIER_QUOTED)) {
            throw new UnprintableNodeException(
                sprintf('Column "%s" cannot be written in FQL', $name),
                self::astNode($node)
            );
        }
        return $text;
    }

    /**
     * FQL strings have no escapes: double quotes unless the value contains one, then single.
     */
    public static function stringLiteral(string $value, ?object $node = null): string
    {
        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }
        throw new UnprintableNodeException(
            sprintf('String %s contains both quote characters and cannot be written in FQL', json_encode($value)),
            self::astNode($node)
        );
    }

    public static function number(int|float $value, ?object $node = null): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (!is_finite($value)) {
            throw new UnprintableNodeException('A non-finite number cannot be written in FQL', self::astNode($node));
        }
        $text = (string) $value;
        if (str_contains($text, 'E') || str_contains($text, 'e')) {
            // FQL numbers have no exponent.
            $text = rtrim(sprintf('%.20F', $value), '0');
        }
        if (!str_contains($text, '.')) {
            $text .= '.0';
        } elseif (str_ends_with($text, '.')) {
            $text .= '0';
        }
        return $text;
    }

    /**
     * Literal text: its original `raw` when that still reads back as the same value,
     * otherwise written from the value.
     */
    public static function literal(LiteralNode $node, KeywordCase $case): string
    {
        if ($node->raw !== '' && self::rawMatches($node)) {
            return $node->raw;
        }
        $value = $node->value;
        return match (true) {
            $value === null => $case->apply('NULL'),
            is_bool($value) => $case->apply($value ? 'TRUE' : 'FALSE'),
            is_int($value), is_float($value) => $node->type === Type::STRING
                ? self::stringLiteral((string) $value, $node)
                : self::number($value, $node),
            is_string($value) => self::stringLiteral($value, $node),
            default => throw new UnprintableNodeException(
                sprintf('A literal of type %s cannot be written in FQL', get_debug_type($value)),
                $node
            ),
        };
    }

    /**
     * Words that never tokenize as an identifier: keywords and TRUE / FALSE / NULL.
     */
    public static function isReservedWord(string $word): bool
    {
        $token = self::singleToken($word);
        return $token === null || $token->type !== TokenType::IDENTIFIER;
    }

    /**
     * The only non-trivia token of `$text`, or null when it tokenizes to anything else.
     */
    public static function singleToken(string $text): ?Token
    {
        try {
            $tokens = (new Tokenizer())->tokenize($text);
        } catch (ParseException) {
            return null;
        }
        $significant = array_values(array_filter(
            $tokens,
            static fn (Token $t): bool => !$t->type->isTrivia() && $t->type !== TokenType::EOF
        ));
        if (count($significant) !== 1 || $significant[0]->raw !== $text) {
            return null;
        }
        return $significant[0];
    }

    private static function rawMatches(LiteralNode $node): bool
    {
        $token = self::singleToken($node->raw);
        if ($token === null) {
            return false;
        }
        return match ($token->type) {
            TokenType::STRING_LITERAL => $node->type === Type::STRING && $node->value === $token->value,
            TokenType::NUMBER_LITERAL => in_array($node->type, [Type::INTEGER, Type::FLOAT, Type::NUMBER], true)
                && Type::matchByString($token->value) === $node->value,
            TokenType::BOOLEAN_LITERAL => $node->type === Type::BOOLEAN
                && $node->value === (strtolower($token->value) === 'true'),
            TokenType::NULL_LITERAL => $node->type === Type::NULL && $node->value === null,
            default => false,
        };
    }

    private static function astNode(?object $node): ?\FQL\Sql\Ast\AstNode
    {
        return $node instanceof \FQL\Sql\Ast\AstNode ? $node : null;
    }
}
