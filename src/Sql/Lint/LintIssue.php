<?php

namespace FQL\Sql\Lint;

use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

/**
 * Single lint finding produced by {@see Linter::lint()}. Immutable value
 * object — position is optional because some rules (e.g. `missing-from`) flag
 * absence of a clause rather than a concrete token.
 *
 * `$span` is the byte range `[start, end)` of the offending token or node in the
 * linted SQL (the function name, the duplicate alias, the file query, the token a
 * syntax error stops at); `$position` keeps its pre-3.3.0 meaning and may point
 * elsewhere (for `duplicate-alias` it is the start of the whole field).
 *
 * `$params` carries the values the message is built from, so a UI can translate by
 * `$rule`: `['function' => …]`, `['alias' => …]`, `['path' => …]`, or the
 * {@see \FQL\Sql\Parser\ParseException} params for `syntax-error`. `$message` stays
 * the English fallback.
 */
final readonly class LintIssue
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public Severity $severity,
        public string $rule,
        public string $message,
        public ?Position $position = null,
        public ?Span $span = null,
        public array $params = [],
    ) {
    }

    public function __toString(): string
    {
        $where = $this->position !== null ? ' at ' . $this->position : '';
        return sprintf(
            '[%s] %s%s — %s',
            strtoupper($this->severity->value),
            $this->rule,
            $where,
            $this->message
        );
    }

    /**
     * JSON-friendly array projection for CLI / API consumers.
     *
     * @return array{
     *     severity: string,
     *     rule: string,
     *     message: string,
     *     line: ?int,
     *     column: ?int,
     *     offset: ?int,
     *     span: array{start: int, end: int}|null,
     *     params: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'rule' => $this->rule,
            'message' => $this->message,
            'line' => $this->position?->line,
            'column' => $this->position?->column,
            'offset' => $this->position?->offset,
            'span' => $this->span !== null ? ['start' => $this->span->start, 'end' => $this->span->end] : null,
            'params' => $this->params,
        ];
    }
}
