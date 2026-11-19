<?php

namespace FQL\Sql\Printer\Internal;

use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Printer\KeywordCase;
use FQL\Sql\Token\Span;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenType;

/**
 * The original SQL of a {@see \FQL\Sql\Printer\SourcePrinter}: its text, its tokens and
 * the set of nodes of the original AST (identity is what marks a node as untouched).
 *
 * @internal
 */
final class SourceIndex
{
    /** @var list<Token> non-trivia tokens in source order (EOF excluded) */
    private readonly array $tokens;

    /** @var list<int> start offsets of {@see $tokens}, for binary search */
    private readonly array $offsets;

    /** @var \WeakMap<object, true> filled once in the constructor */
    private \WeakMap $nodes;

    public readonly KeywordCase $keywordCase;

    public function __construct(public readonly string $sql, SelectStatementNode $root)
    {
        $tokens = [];
        $lower = 0;
        $upper = 0;
        foreach ((new Tokenizer())->tokenize($sql) as $token) {
            if ($token->type->isTrivia() || $token->type === TokenType::EOF) {
                continue;
            }
            $tokens[] = $token;
            if ($token->type->isKeyword()) {
                if ($token->raw === strtolower($token->raw)) {
                    $lower++;
                } elseif ($token->raw === strtoupper($token->raw)) {
                    $upper++;
                }
            }
        }
        $this->tokens = $tokens;
        $this->offsets = array_map(static fn (Token $t): int => $t->position->offset, $tokens);
        $this->keywordCase = $lower > $upper ? KeywordCase::LOWER : KeywordCase::UPPER;

        $this->nodes = new \WeakMap();
        $this->collect($root);
    }

    /**
     * True when `$node` is (the same object as) a node of the original AST and has a span.
     */
    public function isOriginal(object $node): bool
    {
        return $node instanceof HasSpan && $node->span() !== null && isset($this->nodes[$node]);
    }

    public function slice(int $start, int $end): string
    {
        return substr($this->sql, $start, max(0, $end - $start));
    }

    public function sliceSpan(Span $span): string
    {
        return $span->slice($this->sql);
    }

    /**
     * Non-trivia tokens lying completely inside `[start, end)`.
     *
     * @return list<Token>
     */
    public function tokensIn(int $start, int $end): array
    {
        $result = [];
        for ($i = $this->firstIndexAtOrAfter($start), $n = count($this->tokens); $i < $n; $i++) {
            $token = $this->tokens[$i];
            if ($token->position->offset + $token->length > $end) {
                break;
            }
            $result[] = $token;
        }
        return $result;
    }

    /**
     * First non-trivia token starting at or after `$offset`.
     */
    public function tokenAtOrAfter(int $offset): ?Token
    {
        return $this->tokens[$this->firstIndexAtOrAfter($offset)] ?? null;
    }

    private function firstIndexAtOrAfter(int $offset): int
    {
        $low = 0;
        $high = count($this->offsets);
        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($this->offsets[$mid] < $offset) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }
        return $low;
    }

    private function collect(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collect($item);
            }
            return;
        }
        if (!is_object($value) || $value instanceof \UnitEnum || !str_starts_with($value::class, 'FQL\\Sql\\Ast\\')) {
            return;
        }
        if (isset($this->nodes[$value])) {
            return;
        }
        $this->nodes[$value] = true;
        foreach (get_object_vars($value) as $property) {
            $this->collect($property);
        }
    }
}
