<?php

namespace FQL\Sql\Lint;

use FQL\Sql\Token\Position;

/**
 * Single lint finding produced by {@see Linter::lint()}. Immutable value
 * object — position is optional because some rules (e.g. `missing-from`) flag
 * absence of a clause rather than a concrete token.
 */
final readonly class LintIssue
{
    public function __construct(
        public Severity $severity,
        public string $rule,
        public string $message,
        public ?Position $position = null,
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
     * @return array{severity: string, rule: string, message: string, line: ?int, column: ?int, offset: ?int}
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
        ];
    }
}
