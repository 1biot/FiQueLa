<?php

namespace FQL\Sql\Lint;

/**
 * Collection of {@see LintIssue} produced by a single lint run. Provides
 * iteration, counting, severity-based filtering, and JSON-friendly export.
 * Immutable once constructed — rules that want to add more findings should
 * use {@see with()} to derive a new report.
 *
 * @implements \IteratorAggregate<int, LintIssue>
 */
final readonly class LintReport implements \IteratorAggregate, \Countable
{
    /** @var list<LintIssue> */
    public array $issues;

    /**
     * @param list<LintIssue> $issues
     */
    public function __construct(array $issues = [])
    {
        $this->issues = array_values($issues);
    }

    public function hasErrors(): bool
    {
        foreach ($this->issues as $i) {
            if ($i->severity === Severity::ERROR) {
                return true;
            }
        }
        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->issues as $i) {
            if ($i->severity === Severity::WARNING) {
                return true;
            }
        }
        return false;
    }

    public function count(): int
    {
        return count($this->issues);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->issues);
    }

    /**
     * Returns a new report containing only issues at `$min` severity or above.
     */
    public function filterBySeverity(Severity $min): self
    {
        return new self(array_values(array_filter(
            $this->issues,
            static fn (LintIssue $i): bool => $i->severity->isAtLeast($min)
        )));
    }

    /**
     * @param LintIssue|list<LintIssue> $additional
     */
    public function with(LintIssue|array $additional): self
    {
        $new = is_array($additional) ? $additional : [$additional];
        return new self([...$this->issues, ...$new]);
    }

    /**
     * @return list<array{severity: string, rule: string, message: string, line: ?int, column: ?int, offset: ?int}>
     */
    public function toArray(): array
    {
        return array_map(static fn (LintIssue $i): array => $i->toArray(), $this->issues);
    }
}
