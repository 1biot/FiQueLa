<?php

namespace FQL\Sql\Token;

/**
 * Immutable lexical token produced by the Tokenizer.
 *
 * `value` is the normalized form (keywords uppercased, string literals without surrounding quotes).
 * `raw` is the verbatim lexeme as it appears in the source (used by highlighters).
 * `metadata` holds optional auxiliary data (e.g. parsed FileQuery for FILE_QUERY tokens).
 */
final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public string $raw,
        public Position $position,
        public int $length,
        public mixed $metadata = null
    ) {
    }

    public function is(TokenType $type): bool
    {
        return $this->type === $type;
    }

    public function isAnyOf(TokenType ...$types): bool
    {
        return in_array($this->type, $types, true);
    }

    public function __toString(): string
    {
        return sprintf('%s(%s) at %s', $this->type->value, $this->raw, (string) $this->position);
    }
}
