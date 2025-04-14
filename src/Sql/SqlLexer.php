<?php

namespace FQL\Sql;

use FQL\Exception;
use FQL\Query\FileQuery;

/**
 * @implements \Iterator<string>
 */
class SqlLexer implements \Iterator
{
    private const CONTROL_KEYWORDS = [
        'SELECT', 'FROM', 'INNER', 'LEFT', 'JOIN',
        'WHERE', 'GROUP', 'HAVING', 'ORDER',
        'LIMIT', 'OFFSET'
    ];

    /**
     * @var string[]
     */
    protected array $tokens = [];
    protected int $position = 0;

    /**
     * @param string $sql
     * @return string[]
     */
    protected function tokenize(string $sql): array
    {
        // Basic tokenization (can be enhanced for better SQL support)
        // Regex to split SQL while respecting quoted strings
        $regex = '/
            (\b(?!_)[A-Z0-9_]{2,}(?<!_)\(.*?\)   # Function calls (e.g., FUNC_1(arg1)) - name must follow rules
            | ' . FileQuery::getRegexp() . '     # File query regexp
            |\'[^\']*\'                          # Single quoted strings
            | "[^"]*"                            # Double quoted strings
            | [(),]                              # Parentheses and commas
            | \b(AND|OR|XOR)\b                   # Logical operators as whole words
            | [^\s\'"(),]+                       # All other non-whitespace tokens
            | \s+)                               # Whitespace (to split tokens)
        /xi';

        preg_match_all($regex, $sql, $matches);
        // Remove empty tokens and trim
        $this->tokens = array_values(array_filter(array_map('trim', $matches[0]), fn ($value) => $value !== ''));
        return $this->tokens;
    }

    protected function nextToken(): string
    {
        return $this->tokens[$this->position++] ?? '';
    }

    protected function rewindToken(): string
    {
        return $this->tokens[$this->position--] ?? '';
    }

    protected function peekToken(): string
    {
        return $this->tokens[$this->position] ?? '';
    }

    /**
     * @throws Exception\UnexpectedValueException
     */
    protected function expect(string $expected): void
    {
        $token = $this->nextToken();
        if (strtoupper($token) !== strtoupper($expected)) {
            throw new Exception\UnexpectedValueException("Expected $expected, got $token");
        }
    }

    protected function isEOF(): bool
    {
        return $this->position >= count($this->tokens);
    }

    protected function isNextControlledKeyword(): bool
    {
        return in_array(strtoupper($this->peekToken()), self::CONTROL_KEYWORDS);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return $this->isEOF();
    }

    public function next(): void
    {
        $this->nextToken();
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function current(): mixed
    {
        return $this->peekToken();
    }
}
