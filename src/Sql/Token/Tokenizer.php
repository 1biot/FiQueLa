<?php

namespace FQL\Sql\Token;

use FQL\Exception;
use FQL\Query\FileQuery;
use FQL\Sql\Parser\ParseException;

/**
 * Single-pass character-scanning lexer for FQL.
 *
 * Converts the raw SQL string into a sequence of typed tokens with source
 * positions (line/column/offset). Whitespace and comments are emitted as
 * trivia tokens; the TokenStream consumer decides whether to retain or skip
 * them.
 *
 * The tokenizer is **context-sensitive only for FILE_QUERY**: after a
 * `FROM`/`INTO`/`DESCRIBE`/`JOIN` keyword the next non-trivia identifier-like
 * sequence is interpreted as a `FILE_QUERY` token (which encapsulates the
 * full `format(path[, args]).query` syntax) rather than a function call or
 * dotted identifier.
 */
final class Tokenizer
{
    /**
     * Map of upper-cased keyword text to its TokenType.
     *
     * @var array<string, TokenType>
     */
    private const KEYWORDS = [
        'SELECT' => TokenType::KEYWORD_SELECT,
        'FROM' => TokenType::KEYWORD_FROM,
        'WHERE' => TokenType::KEYWORD_WHERE,
        'GROUP' => TokenType::KEYWORD_GROUP,
        'BY' => TokenType::KEYWORD_BY,
        'HAVING' => TokenType::KEYWORD_HAVING,
        'ORDER' => TokenType::KEYWORD_ORDER,
        'LIMIT' => TokenType::KEYWORD_LIMIT,
        'OFFSET' => TokenType::KEYWORD_OFFSET,
        'UNION' => TokenType::KEYWORD_UNION,
        'ALL' => TokenType::KEYWORD_ALL,
        'INTO' => TokenType::KEYWORD_INTO,
        'DESCRIBE' => TokenType::KEYWORD_DESCRIBE,
        'EXPLAIN' => TokenType::KEYWORD_EXPLAIN,
        'ANALYZE' => TokenType::KEYWORD_ANALYZE,
        'DISTINCT' => TokenType::KEYWORD_DISTINCT,
        'EXCLUDE' => TokenType::KEYWORD_EXCLUDE,
        'INNER' => TokenType::KEYWORD_INNER,
        'LEFT' => TokenType::KEYWORD_LEFT,
        'RIGHT' => TokenType::KEYWORD_RIGHT,
        'FULL' => TokenType::KEYWORD_FULL,
        'OUTER' => TokenType::KEYWORD_OUTER,
        'JOIN' => TokenType::KEYWORD_JOIN,
        'ON' => TokenType::KEYWORD_ON,
        'AS' => TokenType::KEYWORD_AS,
        'ASC' => TokenType::KEYWORD_ASC,
        'DESC' => TokenType::KEYWORD_DESC,
        'CASE' => TokenType::KEYWORD_CASE,
        'WHEN' => TokenType::KEYWORD_WHEN,
        'THEN' => TokenType::KEYWORD_THEN,
        'ELSE' => TokenType::KEYWORD_ELSE,
        'END' => TokenType::KEYWORD_END,
        'AND' => TokenType::KEYWORD_AND,
        'OR' => TokenType::KEYWORD_OR,
        'XOR' => TokenType::KEYWORD_XOR,
        'NOT' => TokenType::KEYWORD_NOT,
        'IS' => TokenType::KEYWORD_IS,
        'IN' => TokenType::KEYWORD_IN,
        'LIKE' => TokenType::KEYWORD_LIKE,
        'BETWEEN' => TokenType::KEYWORD_BETWEEN,
        'REGEXP' => TokenType::KEYWORD_REGEXP,
        // Note: AGAINST is intentionally *not* mapped here. The tokenizer emits it as
        // FUNCTION_NAME when followed by `(`, so the parser can treat MATCH(...) and
        // AGAINST(...) symmetrically. KEYWORD_AGAINST remains in TokenType for future
        // use (e.g. dedicated highlighter styling), but is never produced.
    ];

    /** @var Token[] */
    private array $tokens = [];

    private string $sql = '';
    private int $length = 0;
    private int $position = 0;
    private int $line = 1;
    private int $column = 1;

    /** Whether the next non-trivia token should be parsed as a FILE_QUERY source reference. */
    private bool $expectFileQuery = false;

    /**
     * @return Token[]
     * @throws ParseException
     */
    public function tokenize(string $sql): array
    {
        $this->sql = $sql;
        $this->length = strlen($sql);
        $this->position = 0;
        $this->line = 1;
        $this->column = 1;
        $this->tokens = [];
        $this->expectFileQuery = false;

        while ($this->position < $this->length) {
            $this->scanNext();
        }

        $this->tokens[] = new Token(
            TokenType::EOF,
            '',
            '',
            new Position($this->position, $this->line, $this->column),
            0
        );

        return $this->tokens;
    }

    /**
     * @throws ParseException
     */
    private function scanNext(): void
    {
        $startOffset = $this->position;
        $startLine = $this->line;
        $startColumn = $this->column;
        $char = $this->sql[$this->position];

        // Whitespace
        if (ctype_space($char)) {
            $this->scanWhitespace($startOffset, $startLine, $startColumn);
            return;
        }

        // Comments
        if ($char === '-' && $this->peekChar(1) === '-') {
            $this->scanLineComment($startOffset, $startLine, $startColumn);
            return;
        }
        if ($char === '#') {
            $this->scanLineComment($startOffset, $startLine, $startColumn);
            return;
        }
        if ($char === '/' && $this->peekChar(1) === '*') {
            $this->scanBlockComment($startOffset, $startLine, $startColumn);
            return;
        }

        // Punctuation
        if ($char === '(') {
            $this->advance();
            $this->emit(TokenType::PAREN_OPEN, '(', '(', $startOffset, $startLine, $startColumn, 1);
            return;
        }
        if ($char === ')') {
            $this->advance();
            $this->emit(TokenType::PAREN_CLOSE, ')', ')', $startOffset, $startLine, $startColumn, 1);
            return;
        }
        if ($char === ',') {
            $this->advance();
            $this->emit(TokenType::COMMA, ',', ',', $startOffset, $startLine, $startColumn, 1);
            return;
        }
        if ($char === '*') {
            $this->advance();
            $this->emit(TokenType::STAR, '*', '*', $startOffset, $startLine, $startColumn, 1);
            return;
        }

        // Operators
        if ($char === '!' || $char === '=' || $char === '<' || $char === '>') {
            $this->scanOperator($startOffset, $startLine, $startColumn);
            return;
        }

        // String literals
        if ($char === '\'' || $char === '"') {
            $this->scanStringLiteral($char, $startOffset, $startLine, $startColumn);
            return;
        }

        // Backtick-quoted identifier
        if ($char === '`') {
            $this->scanBacktickIdentifier($startOffset, $startLine, $startColumn);
            return;
        }

        // Numbers (with optional leading sign in number-expecting context)
        if (ctype_digit($char)) {
            $this->scanNumber($startOffset, $startLine, $startColumn);
            return;
        }
        if (($char === '-' || $char === '+') && $this->isCharDigit($this->peekChar(1))) {
            $previous = $this->lastNonTriviaTokenType();
            if ($this->canPrefixSignedNumber($previous)) {
                $this->scanNumber($startOffset, $startLine, $startColumn);
                return;
            }
        }

        // Numbers starting with `.`
        if ($char === '.' && $this->isCharDigit($this->peekChar(1))) {
            $this->scanNumber($startOffset, $startLine, $startColumn);
            return;
        }

        // Arithmetic operators (after the signed-number lookahead above, so `-5` in
        // expression context still scans as a single NUMBER_LITERAL).
        if ($char === '+') {
            $this->advance();
            $this->emit(TokenType::OP_PLUS, '+', '+', $startOffset, $startLine, $startColumn, 1);
            return;
        }
        if ($char === '-') {
            $this->advance();
            $this->emit(TokenType::OP_MINUS, '-', '-', $startOffset, $startLine, $startColumn, 1);
            return;
        }
        if ($char === '/') {
            $this->advance();
            $this->emit(TokenType::OP_SLASH, '/', '/', $startOffset, $startLine, $startColumn, 1);
            return;
        }
        if ($char === '%') {
            $this->advance();
            $this->emit(TokenType::OP_PERCENT, '%', '%', $startOffset, $startLine, $startColumn, 1);
            return;
        }

        // Identifiers / keywords / function names / file queries
        if ($this->isIdentifierStart($char)) {
            $this->scanIdentifierLike($startOffset, $startLine, $startColumn);
            return;
        }

        $position = new Position($startOffset, $startLine, $startColumn);
        throw new ParseException(
            new Token(TokenType::EOF, $char, $char, $position, 1),
            [],
            sprintf('Unexpected character "%s" at %s', $char, (string) $position)
        );
    }

    private function scanWhitespace(int $startOffset, int $startLine, int $startColumn): void
    {
        $start = $this->position;
        while ($this->position < $this->length && ctype_space($this->sql[$this->position])) {
            $this->advance();
        }
        $raw = substr($this->sql, $start, $this->position - $start);
        $length = $this->position - $start;
        $this->emit(TokenType::WHITESPACE, $raw, $raw, $startOffset, $startLine, $startColumn, $length);
    }

    private function scanLineComment(int $startOffset, int $startLine, int $startColumn): void
    {
        $start = $this->position;
        while ($this->position < $this->length && $this->sql[$this->position] !== "\n") {
            $this->advance();
        }
        $raw = substr($this->sql, $start, $this->position - $start);
        $length = $this->position - $start;
        $this->emit(TokenType::COMMENT_LINE, $raw, $raw, $startOffset, $startLine, $startColumn, $length);
    }

    /**
     * @throws ParseException
     */
    private function scanBlockComment(int $startOffset, int $startLine, int $startColumn): void
    {
        $start = $this->position;
        $this->advance(); // consume `/`
        $this->advance(); // consume `*`
        while ($this->position < $this->length) {
            if ($this->sql[$this->position] === '*' && $this->peekChar(1) === '/') {
                $this->advance();
                $this->advance();
                $raw = substr($this->sql, $start, $this->position - $start);
                $this->emit(
                    TokenType::COMMENT_BLOCK,
                    $raw,
                    $raw,
                    $startOffset,
                    $startLine,
                    $startColumn,
                    $this->position - $start
                );
                return;
            }
            $this->advance();
        }
        $position = new Position($startOffset, $startLine, $startColumn);
        throw new ParseException(
            new Token(TokenType::EOF, '', '', $position, 0),
            [],
            sprintf('Unterminated block comment starting at %s', (string) $position)
        );
    }

    /**
     * @throws ParseException
     */
    private function scanOperator(int $startOffset, int $startLine, int $startColumn): void
    {
        $first = $this->sql[$this->position];
        $second = $this->peekChar(1);
        $third = $this->peekChar(2);

        // Three-char operators: !==, <=>?
        if ($first === '!' && $second === '=' && $third === '=') {
            $this->advance();
            $this->advance();
            $this->advance();
            $this->emit(TokenType::OP_NEQ_STRICT, '!==', '!==', $startOffset, $startLine, $startColumn, 3);
            return;
        }
        if ($first === '=' && $second === '=' && $third === '=') {
            // Treat === as strict equality variant; not supported in FQL but emit for clarity.
            $this->advance();
            $this->advance();
            $this->advance();
            $this->emit(TokenType::OP_EQ_STRICT, '===', '===', $startOffset, $startLine, $startColumn, 3);
            return;
        }

        // Two-char operators
        if ($first === '!' && $second === '=') {
            $this->advance();
            $this->advance();
            $this->emit(TokenType::OP_NEQ, '!=', '!=', $startOffset, $startLine, $startColumn, 2);
            return;
        }
        if ($first === '=' && $second === '=') {
            $this->advance();
            $this->advance();
            $this->emit(TokenType::OP_EQ_STRICT, '==', '==', $startOffset, $startLine, $startColumn, 2);
            return;
        }
        if ($first === '<' && $second === '=') {
            $this->advance();
            $this->advance();
            $this->emit(TokenType::OP_LTE, '<=', '<=', $startOffset, $startLine, $startColumn, 2);
            return;
        }
        if ($first === '>' && $second === '=') {
            $this->advance();
            $this->advance();
            $this->emit(TokenType::OP_GTE, '>=', '>=', $startOffset, $startLine, $startColumn, 2);
            return;
        }
        if ($first === '<' && $second === '>') {
            // SQL standard for not-equal; emit as OP_NEQ for parser convenience.
            $this->advance();
            $this->advance();
            $this->emit(TokenType::OP_NEQ, '<>', '<>', $startOffset, $startLine, $startColumn, 2);
            return;
        }

        // Single-char operators
        if ($first === '=') {
            $this->advance();
            $this->emit(TokenType::OP_EQ, '=', '=', $startOffset, $startLine, $startColumn, 1);
            return;
        }
        if ($first === '<') {
            $this->advance();
            $this->emit(TokenType::OP_LT, '<', '<', $startOffset, $startLine, $startColumn, 1);
            return;
        }
        if ($first === '>') {
            $this->advance();
            $this->emit(TokenType::OP_GT, '>', '>', $startOffset, $startLine, $startColumn, 1);
            return;
        }

        $position = new Position($startOffset, $startLine, $startColumn);
        throw new ParseException(
            new Token(TokenType::EOF, $first, $first, $position, 1),
            [],
            sprintf('Unexpected operator character "%s" at %s', $first, (string) $position)
        );
    }

    /**
     * @throws ParseException
     */
    private function scanStringLiteral(string $quote, int $startOffset, int $startLine, int $startColumn): void
    {
        $start = $this->position;
        $this->advance(); // opening quote

        while ($this->position < $this->length) {
            $current = $this->sql[$this->position];
            if ($current === $quote) {
                $this->advance(); // closing quote
                $raw = substr($this->sql, $start, $this->position - $start);
                $value = substr($raw, 1, strlen($raw) - 2);
                $this->emit(
                    TokenType::STRING_LITERAL,
                    $value,
                    $raw,
                    $startOffset,
                    $startLine,
                    $startColumn,
                    $this->position - $start
                );
                return;
            }
            $this->advance();
        }

        $position = new Position($startOffset, $startLine, $startColumn);
        throw new ParseException(
            new Token(TokenType::EOF, $quote, $quote, $position, 1),
            [],
            sprintf('Unterminated string literal starting at %s', (string) $position)
        );
    }

    /**
     * @throws ParseException
     */
    private function scanBacktickIdentifier(int $startOffset, int $startLine, int $startColumn): void
    {
        $start = $this->position;
        $this->advance(); // opening backtick

        while ($this->position < $this->length) {
            if ($this->sql[$this->position] === '`') {
                $this->advance();
                $raw = substr($this->sql, $start, $this->position - $start);
                $value = substr($raw, 1, strlen($raw) - 2);
                $this->emit(
                    TokenType::IDENTIFIER_QUOTED,
                    $value,
                    $raw,
                    $startOffset,
                    $startLine,
                    $startColumn,
                    $this->position - $start
                );
                return;
            }
            $this->advance();
        }

        $position = new Position($startOffset, $startLine, $startColumn);
        throw new ParseException(
            new Token(TokenType::EOF, '`', '`', $position, 1),
            [],
            sprintf('Unterminated backtick identifier starting at %s', (string) $position)
        );
    }

    private function scanNumber(int $startOffset, int $startLine, int $startColumn): void
    {
        $start = $this->position;
        if ($this->sql[$this->position] === '-' || $this->sql[$this->position] === '+') {
            $this->advance();
        }
        while ($this->position < $this->length && ctype_digit($this->sql[$this->position])) {
            $this->advance();
        }
        if ($this->position < $this->length && $this->sql[$this->position] === '.') {
            $this->advance();
            while ($this->position < $this->length && ctype_digit($this->sql[$this->position])) {
                $this->advance();
            }
        }
        $raw = substr($this->sql, $start, $this->position - $start);
        $length = $this->position - $start;
        $this->emit(TokenType::NUMBER_LITERAL, $raw, $raw, $startOffset, $startLine, $startColumn, $length);
    }

    private function scanIdentifierLike(int $startOffset, int $startLine, int $startColumn): void
    {
        // FILE_QUERY context (after FROM/INTO/JOIN/DESCRIBE)
        if ($this->expectFileQuery) {
            $matched = $this->tryMatchFileQuery();
            if ($matched !== null) {
                $this->expectFileQuery = false;
                $startPos = new Position($startOffset, $startLine, $startColumn);
                $length = strlen($matched);
                // Lazy-construct FileQuery to expose parsed metadata; tolerate failure.
                $metadata = null;
                try {
                    $metadata = new FileQuery($matched);
                } catch (Exception\FileQueryException | Exception\InvalidFormatException) {
                    $metadata = null;
                }
                for ($i = 0; $i < $length; $i++) {
                    $this->advance();
                }
                $this->tokens[] = new Token(
                    TokenType::FILE_QUERY,
                    $matched,
                    $matched,
                    $startPos,
                    $length,
                    $metadata
                );
                return;
            }
            // Fall through: not actually a file query, treat as plain identifier
            $this->expectFileQuery = false;
        }

        $word = $this->scanIdentifierChain();
        $upper = strtoupper($word);

        // Keyword?
        if (isset(self::KEYWORDS[$upper])) {
            $type = self::KEYWORDS[$upper];
            $this->emit($type, $upper, $word, $startOffset, $startLine, $startColumn, strlen($word));
            // Activate FILE_QUERY context after source-introducing keywords.
            if (
                $type === TokenType::KEYWORD_FROM
                || $type === TokenType::KEYWORD_INTO
                || $type === TokenType::KEYWORD_DESCRIBE
                || $type === TokenType::KEYWORD_JOIN
            ) {
                $this->expectFileQuery = true;
            }
            return;
        }

        // Boolean / null literals
        if ($upper === 'TRUE' || $upper === 'FALSE') {
            $this->emit(
                TokenType::BOOLEAN_LITERAL,
                strtolower($word),
                $word,
                $startOffset,
                $startLine,
                $startColumn,
                strlen($word)
            );
            return;
        }
        if ($upper === 'NULL') {
            $this->emit(TokenType::NULL_LITERAL, 'null', $word, $startOffset, $startLine, $startColumn, strlen($word));
            return;
        }

        // Function name: identifier immediately followed by `(`
        if ($this->position < $this->length && $this->sql[$this->position] === '(') {
            $this->emit(TokenType::FUNCTION_NAME, $upper, $word, $startOffset, $startLine, $startColumn, strlen($word));
            return;
        }

        // Plain identifier (may be dotted)
        $this->emit(TokenType::IDENTIFIER, $word, $word, $startOffset, $startLine, $startColumn, strlen($word));
    }

    /**
     * Attempts to match the FileQuery regex starting at the current position.
     * Returns the matched substring or null if no FileQuery-shaped token starts here.
     *
     * The FileQuery regex permits a path-only match (no `format(...)` prefix) like
     * `data.users`, but we reject standalone reserved keywords (`SELECT`, `FROM`, ...)
     * to avoid swallowing them when the FILE_QUERY context lingers across structural
     * tokens (e.g. `JOIN (SELECT ...)`).
     */
    private function tryMatchFileQuery(): ?string
    {
        $remainder = substr($this->sql, $this->position);
        $pattern = '/^' . FileQuery::getRegexp() . '/u';
        if (preg_match($pattern, $remainder, $matches) !== 1) {
            return null;
        }
        $match = $matches['fq'] ?? '';
        if ($match === '') {
            return null;
        }
        $hasFormatPrefix = ($matches['fs'] ?? '') !== '';
        if (!$hasFormatPrefix && isset(self::KEYWORDS[strtoupper($match)])) {
            return null;
        }
        return $match;
    }

    /**
     * Scans an identifier with optional dot-chain segments (e.g. `data.users.name`, `data.*`).
     */
    private function scanIdentifierChain(): string
    {
        $start = $this->position;
        $this->scanIdentifierWord();

        while ($this->position < $this->length && $this->sql[$this->position] === '.') {
            // Look one character ahead to ensure the dot continues the chain.
            $next = $this->peekChar(1);
            if ($next === '*') {
                $this->advance(); // consume '.'
                $this->advance(); // consume '*'
                continue;
            }
            if ($next !== null && $this->isIdentifierStart($next)) {
                $this->advance(); // consume '.'
                $this->scanIdentifierWord();
                continue;
            }
            break;
        }

        return substr($this->sql, $start, $this->position - $start);
    }

    private function scanIdentifierWord(): void
    {
        while ($this->position < $this->length && $this->isIdentifierChar($this->sql[$this->position])) {
            $this->advance();
        }
    }

    private function isIdentifierStart(string $char): bool
    {
        // `@` supports XML attribute-style references such as `@attributes.id`; `$` is
        // common in document-style accessors.
        return ctype_alpha($char) || $char === '_' || $char === '@' || $char === '$';
    }

    private function isIdentifierChar(string $char): bool
    {
        // `-` is permitted mid-identifier to support kebab-case field names that appear
        // in real-world files (e.g. `order-total`, `product-id`).
        return ctype_alnum($char)
            || $char === '_'
            || $char === '@'
            || $char === '$'
            || $char === '-';
    }

    private function isCharDigit(?string $char): bool
    {
        return $char !== null && ctype_digit($char);
    }

    private function canPrefixSignedNumber(?TokenType $previous): bool
    {
        if ($previous === null) {
            return true;
        }
        return $previous === TokenType::PAREN_OPEN
            || $previous === TokenType::COMMA
            || $previous->isOperator()
            || $previous->isKeyword();
    }

    private function lastNonTriviaTokenType(): ?TokenType
    {
        for ($i = count($this->tokens) - 1; $i >= 0; $i--) {
            $type = $this->tokens[$i]->type;
            if (!$type->isTrivia()) {
                return $type;
            }
        }
        return null;
    }

    private function peekChar(int $offset): ?string
    {
        $pos = $this->position + $offset;
        return $pos < $this->length ? $this->sql[$pos] : null;
    }

    private function advance(): void
    {
        if ($this->position >= $this->length) {
            return;
        }
        $char = $this->sql[$this->position];
        if ($char === "\n") {
            $this->line++;
            $this->column = 1;
        } else {
            $this->column++;
        }
        $this->position++;
    }

    private function emit(
        TokenType $type,
        string $value,
        string $raw,
        int $offset,
        int $line,
        int $column,
        int $length
    ): void {
        $this->tokens[] = new Token($type, $value, $raw, new Position($offset, $line, $column), $length);
        // Drop the FILE_QUERY expectation as soon as we emit any non-trivia token.
        // Identifier-like emitters (where FILE_QUERY can actually be produced) handle
        // the flag themselves; here we cover the case where structural tokens like
        // PAREN_OPEN or COMMA appear directly after a source-introducing keyword.
        if ($this->expectFileQuery && !$type->isTrivia() && $type !== TokenType::FILE_QUERY) {
            $this->expectFileQuery = false;
        }
    }
}
