<?php

namespace FQL\Sql\Highlighter;

use FQL\Sql\Token\TokenType;

/**
 * HTML theme. Wraps each styled token in a `<span class="fql-…">` so consumers can
 * colour it via their own CSS. Content is `htmlspecialchars`-escaped.
 *
 * Default class mapping:
 *   - `fql-keyword` / `fql-keyword-logical` — SQL keywords (SELECT/AND/...)
 *   - `fql-function` — function names
 *   - `fql-identifier` / `fql-identifier-quoted` — column references
 *   - `fql-file-query` — FILE_QUERY tokens (source references)
 *   - `fql-string`, `fql-number`, `fql-boolean`, `fql-null` — literals
 *   - `fql-operator` — comparison/arithmetic operators
 *   - `fql-comment` — line + block comments
 *   - `fql-punctuation` — parentheses / commas / stars
 */
final class HtmlTheme implements Theme
{
    public function styleStart(TokenType $type): string
    {
        $class = $this->classFor($type);
        return $class === null ? '' : sprintf('<span class="%s">', $class);
    }

    public function styleEnd(TokenType $type): string
    {
        return $this->classFor($type) === null ? '' : '</span>';
    }

    public function escape(string $raw): string
    {
        return htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function classFor(TokenType $type): ?string
    {
        return match (true) {
            $type === TokenType::FUNCTION_NAME => 'fql-function',
            $type === TokenType::FILE_QUERY => 'fql-file-query',
            $type === TokenType::STRING_LITERAL => 'fql-string',
            $type === TokenType::NUMBER_LITERAL => 'fql-number',
            $type === TokenType::BOOLEAN_LITERAL => 'fql-boolean',
            $type === TokenType::NULL_LITERAL => 'fql-null',
            $type === TokenType::IDENTIFIER_QUOTED => 'fql-identifier-quoted',
            $type === TokenType::IDENTIFIER => 'fql-identifier',
            $type === TokenType::COMMENT_LINE,
            $type === TokenType::COMMENT_BLOCK => 'fql-comment',
            $type->isOperator() => 'fql-operator',
            $type === TokenType::COMMA,
            $type === TokenType::PAREN_OPEN,
            $type === TokenType::PAREN_CLOSE,
            $type === TokenType::STAR => 'fql-punctuation',
            $type === TokenType::KEYWORD_AND,
            $type === TokenType::KEYWORD_OR,
            $type === TokenType::KEYWORD_XOR,
            $type === TokenType::KEYWORD_NOT,
            $type === TokenType::KEYWORD_IS,
            $type === TokenType::KEYWORD_IN,
            $type === TokenType::KEYWORD_LIKE,
            $type === TokenType::KEYWORD_BETWEEN,
            $type === TokenType::KEYWORD_REGEXP => 'fql-keyword-logical',
            $type->isKeyword() => 'fql-keyword',
            default => null,
        };
    }
}
