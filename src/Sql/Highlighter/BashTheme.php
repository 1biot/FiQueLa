<?php

namespace FQL\Sql\Highlighter;

use FQL\Sql\Token\TokenType;

/**
 * ANSI-colour theme for Bash / terminal output.
 *
 * Colours are chosen to be readable on both dark and light terminal backgrounds;
 * style choices:
 *   - structural keywords (SELECT/FROM/...): bold cyan
 *   - logical keywords (AND/OR/NOT/IN/IS): bold yellow
 *   - function names: magenta
 *   - identifiers: default (no style)
 *   - file queries: bold blue (to flag source references)
 *   - string literals: green
 *   - number/boolean/null literals: cyan
 *   - operators: magenta
 *   - comments: dim grey
 */
final class BashTheme implements Theme
{
    private const RESET = "\e[0m";

    public function styleStart(TokenType $type): string
    {
        return match (true) {
            $type === TokenType::FUNCTION_NAME => "\e[35m",
            $type === TokenType::FILE_QUERY => "\e[1;34m",
            $type === TokenType::STRING_LITERAL => "\e[32m",
            $type === TokenType::NUMBER_LITERAL,
            $type === TokenType::BOOLEAN_LITERAL,
            $type === TokenType::NULL_LITERAL => "\e[36m",
            $type === TokenType::IDENTIFIER_QUOTED => "\e[3;37m",
            $type === TokenType::COMMENT_LINE,
            $type === TokenType::COMMENT_BLOCK => "\e[2;37m",
            $type->isOperator() => "\e[35m",
            $type === TokenType::KEYWORD_AND,
            $type === TokenType::KEYWORD_OR,
            $type === TokenType::KEYWORD_XOR,
            $type === TokenType::KEYWORD_NOT,
            $type === TokenType::KEYWORD_IS,
            $type === TokenType::KEYWORD_IN,
            $type === TokenType::KEYWORD_LIKE,
            $type === TokenType::KEYWORD_BETWEEN,
            $type === TokenType::KEYWORD_REGEXP => "\e[1;33m",
            $type->isKeyword() => "\e[1;36m",
            default => '',
        };
    }

    public function styleEnd(TokenType $type): string
    {
        return $this->styleStart($type) === '' ? '' : self::RESET;
    }

    public function escape(string $raw): string
    {
        return $raw;
    }
}
