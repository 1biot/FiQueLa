<?php

namespace FQL\Sql\Support;

/**
 * Splits a comma-separated field list into individual field strings while respecting
 * parentheses, single/double/backtick quotes, and square brackets (used for array
 * accessors like `tags[]`).
 *
 * Replaces the legacy `SqlLexer::tokenize()` use in `Traits\Select::select/exclude`:
 * a character-level scanner is used (rather than the full FQL tokenizer) so that
 * arbitrary non-word characters typical of user field names — e.g. `categories[]->name`,
 * `#hash_field`, `field/segment` — pass through unchanged.
 */
final class FieldListSplitter
{
    /**
     * @param string ...$fields one or more field expressions; commas inside them are honoured.
     * @return string[] trimmed non-empty top-level field expressions in input order.
     */
    public static function split(string ...$fields): array
    {
        $segments = [];
        foreach ($fields as $input) {
            if ($input === '') {
                continue;
            }
            foreach (self::splitOne($input) as $segment) {
                $segments[] = $segment;
            }
        }
        return $segments;
    }

    /**
     * @return string[]
     */
    private static function splitOne(string $input): array
    {
        $result = [];
        $buffer = '';
        $parenDepth = 0;
        $bracketDepth = 0;
        $quoteChar = null;
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];

            if ($quoteChar !== null) {
                $buffer .= $char;
                if ($char === $quoteChar) {
                    $quoteChar = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'" || $char === '`') {
                $quoteChar = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                $buffer .= $char;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                $buffer .= $char;
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                $buffer .= $char;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === ',' && $parenDepth === 0 && $bracketDepth === 0) {
                $segment = trim($buffer);
                if ($segment !== '') {
                    $result[] = $segment;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $segment = trim($buffer);
        if ($segment !== '') {
            $result[] = $segment;
        }
        return $result;
    }

    /**
     * Splits an `<expr> AS <alias>` spec. The alias is detected only at the top level
     * (outside quotes/parens/brackets); if no AS is found, `alias` is null.
     *
     * @return array{field: string, alias: ?string}
     */
    public static function splitAlias(string $spec): array
    {
        $length = strlen($spec);
        $quoteChar = null;
        $parenDepth = 0;
        $bracketDepth = 0;

        // Scan for the rightmost top-level `AS` (case-insensitive) surrounded by whitespace.
        $asPos = -1;
        for ($i = 0; $i < $length - 3; $i++) {
            $char = $spec[$i];
            if ($quoteChar !== null) {
                if ($char === $quoteChar) {
                    $quoteChar = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quoteChar = $char;
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }
            if ($parenDepth !== 0 || $bracketDepth !== 0) {
                continue;
            }
            // Match " AS " or " as " at a word boundary.
            if (
                ctype_space($char)
                && strcasecmp(substr($spec, $i + 1, 2), 'AS') === 0
                && isset($spec[$i + 3])
                && ctype_space($spec[$i + 3])
            ) {
                $asPos = $i + 1;
            }
        }

        if ($asPos === -1) {
            return ['field' => trim($spec), 'alias' => null];
        }

        $field = trim(substr($spec, 0, $asPos));
        $alias = trim(substr($spec, $asPos + 3));
        // Strip backticks from the alias for consistent lookup keys.
        if (strlen($alias) >= 2 && $alias[0] === '`' && $alias[-1] === '`') {
            $alias = substr($alias, 1, -1);
        }
        if ($field === '' || $alias === '') {
            return ['field' => trim($spec), 'alias' => null];
        }
        return ['field' => $field, 'alias' => $alias];
    }
}
