<?php

namespace FQL\Sql\Highlighter;

use FQL\Sql\Token\TokenType;

/**
 * Renders styling around individual tokens. Implementations decide the output medium
 * (ANSI escape codes, HTML spans, plain strings, ...) by returning the open/close
 * markers emitted around each token's raw lexeme.
 *
 * Themes should be safe to call for any TokenType; the default return of `''` for
 * unstyled tokens (whitespace, trivia) is expected.
 */
interface Theme
{
    /**
     * String to emit before a token's raw lexeme (e.g. ANSI colour code or `<span>`).
     */
    public function styleStart(TokenType $type): string;

    /**
     * String to emit after a token's raw lexeme (e.g. ANSI reset or `</span>`).
     */
    public function styleEnd(TokenType $type): string;

    /**
     * Optional per-token content transform — typically identity for Bash and
     * `htmlspecialchars` for HTML.
     */
    public function escape(string $raw): string;
}
