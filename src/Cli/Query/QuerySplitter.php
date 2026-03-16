<?php

namespace FQL\Cli\Query;

/**
 * Splits SQL-like query strings on semicolons while respecting quoted strings.
 * Handles both single (') and double (") quotes, as well as parentheses
 * that may contain semicolons (e.g., [csv](file.csv, utf-8, ";")).
 */
class QuerySplitter
{
    /**
     * Check if the buffer contains a terminating semicolon (outside of quotes).
     */
    public static function hasTerminatingSemicolon(string $buffer): bool
    {
        $trimmed = rtrim($buffer);
        if ($trimmed === '' || $trimmed[strlen($trimmed) - 1] !== ';') {
            return false;
        }

        // Verify the semicolon is not inside quotes
        $inSingleQuote = false;
        $inDoubleQuote = false;

        for ($i = 0, $len = strlen($trimmed); $i < $len; $i++) {
            $char = $trimmed[$i];

            if ($char === '\\' && $i + 1 < $len) {
                $i++; // skip escaped character
                continue;
            }

            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
            }
        }

        // The last semicolon is a terminator only if we're not inside quotes
        return !$inSingleQuote && !$inDoubleQuote;
    }

    /**
     * Split a query string on semicolons while respecting quoted strings.
     * Returns non-empty trimmed query parts.
     *
     * @return string[]
     */
    public static function split(string $input): array
    {
        $queries = [];
        $current = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $char = $input[$i];

            if ($char === '\\' && $i + 1 < $len) {
                $current .= $char . $input[$i + 1];
                $i++;
                continue;
            }

            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $current .= $char;
            } elseif ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $current .= $char;
            } elseif ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $queries[] = $trimmed;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // Add remaining content
        $trimmed = trim($current);
        if ($trimmed !== '') {
            $queries[] = $trimmed;
        }

        return $queries;
    }

    /**
     * Strip the trailing semicolon from a buffer (outside of quotes).
     */
    public static function stripTrailingSemicolon(string $buffer): string
    {
        $trimmed = rtrim($buffer);
        if ($trimmed !== '' && $trimmed[strlen($trimmed) - 1] === ';') {
            return rtrim(substr($trimmed, 0, -1));
        }
        return $trimmed;
    }
}
