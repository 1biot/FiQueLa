<?php

namespace FQL\Sql\Token;

use FQL\Sql\Parser\ParseException;

/**
 * Cursor over a sequence of tokens produced by the Tokenizer.
 *
 * In the default mode (`includeTrivia: false`) the cursor transparently skips
 * WHITESPACE/COMMENT tokens so the parser sees only meaningful content.
 * Highlighters and formatters instantiate the stream with `includeTrivia: true`
 * to retain the full sequence verbatim.
 *
 * The stream is read-only; mark()/rewindTo() allow speculative parsing.
 *
 * @implements \Iterator<int, Token>
 */
final class TokenStream implements \Iterator, \Countable
{
    /** @var Token[] */
    private readonly array $tokens;

    private int $position = 0;

    private readonly Token $eof;

    /**
     * @param Token[] $tokens
     */
    public function __construct(array $tokens, private readonly bool $includeTrivia = false)
    {
        // Filter trivia eagerly when not requested - keeps peek/consume simple and O(1).
        $filtered = $includeTrivia
            ? $tokens
            : array_values(array_filter($tokens, static fn (Token $t): bool => !$t->type->isTrivia()));

        // Guarantee at least one EOF token so peek/consume is safe at the boundary.
        $last = end($filtered);
        if ($last === false || $last->type !== TokenType::EOF) {
            $position = $last instanceof Token
                ? new Position($last->position->offset + $last->length, $last->position->line, $last->position->column + $last->length)
                : new Position(0, 1, 1);
            $filtered[] = new Token(TokenType::EOF, '', '', $position, 0);
        }

        $this->tokens = array_values($filtered);
        $this->eof = $this->tokens[count($this->tokens) - 1];
    }

    public function peek(int $offset = 0): Token
    {
        return $this->tokens[$this->position + $offset] ?? $this->eof;
    }

    public function peekType(int $offset = 0): TokenType
    {
        return $this->peek($offset)->type;
    }

    public function consume(): Token
    {
        $token = $this->tokens[$this->position] ?? $this->eof;
        if ($token->type !== TokenType::EOF) {
            $this->position++;
        }
        return $token;
    }

    public function consumeIf(TokenType ...$types): ?Token
    {
        if ($this->peek()->isAnyOf(...$types)) {
            return $this->consume();
        }
        return null;
    }

    /**
     * @throws ParseException
     */
    public function expect(TokenType ...$types): Token
    {
        $token = $this->peek();
        if (!$token->isAnyOf(...$types)) {
            throw ParseException::unexpected($token, ...$types);
        }
        return $this->consume();
    }

    public function mark(): int
    {
        return $this->position;
    }

    public function rewindTo(int $marker): void
    {
        $this->position = max(0, min($marker, count($this->tokens)));
    }

    public function isAtEnd(): bool
    {
        return $this->peek()->type === TokenType::EOF;
    }

    public function includesTrivia(): bool
    {
        return $this->includeTrivia;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return $this->position < count($this->tokens);
    }

    public function current(): Token
    {
        return $this->peek();
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    /**
     * @return Token[]
     */
    public function toArray(): array
    {
        return $this->tokens;
    }
}
