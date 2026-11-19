<?php

namespace FQL\Sql\Token;

/**
 * Half-open byte range `[start, end)` of a node within the original SQL string.
 *
 * `start` is the offset of the node's first token and `end` the offset just past its
 * last token, so `substr($sql, $span->start, $span->length())` is the node's verbatim
 * source text (comments and whitespace between its tokens included, surrounding
 * trivia excluded). Offsets are bytes, like {@see Position::$offset}.
 *
 * Spans are filled by the parser. Nodes built in code (fluent API, `with…()` copies,
 * hand-made edits) have no span.
 */
final readonly class Span
{
    public function __construct(
        public int $start,
        public int $end
    ) {
        if ($start < 0 || $end < $start) {
            throw new \InvalidArgumentException(sprintf('Invalid span [%d, %d)', $start, $end));
        }
    }

    /**
     * Span from the first byte of `$first` to the last byte of `$last`.
     */
    public static function fromTokens(Token $first, Token $last): self
    {
        $start = $first->position->offset;
        return new self($start, max($start, $last->position->offset + $last->length));
    }

    public function length(): int
    {
        return $this->end - $this->start;
    }

    public function isEmpty(): bool
    {
        return $this->end === $this->start;
    }

    /**
     * True when `$other` lies completely inside this span.
     */
    public function contains(self $other): bool
    {
        return $other->start >= $this->start && $other->end <= $this->end;
    }

    /**
     * The verbatim text of this span in `$sql` (the string the node was parsed from).
     */
    public function slice(string $sql): string
    {
        return substr($sql, $this->start, $this->length());
    }

    public function __toString(): string
    {
        return sprintf('[%d, %d)', $this->start, $this->end);
    }
}
