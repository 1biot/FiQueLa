<?php

namespace UQL\Parser;

class SqlLexer
{
    /**
     * @param string $sql
     * @return string[]
     */
    public function tokenize(string $sql): array
    {
        // Basic tokenization (can be enhanced for better SQL support)
        // Regex to split SQL while respecting quoted strings
        $regex = '/
            (\'[^\']*\'                    # Single quoted strings
            | "[^"]*"                     # Double quoted strings
            | [(),]                       # Parentheses and commas
            | \b(AND|OR)\b                # Logical operators as whole words
            | [^\s\'"(),]+                # All other non-whitespace tokens
            | \s+)                        # Whitespace (to split tokens)
        /xi';

        preg_match_all($regex, $sql, $matches);

        // Remove empty tokens and trim
        return array_values(array_filter(array_map('trim', $matches[0])));
    }
}
