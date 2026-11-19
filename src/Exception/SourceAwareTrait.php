<?php

namespace FQL\Exception;

use FQL\Sql\Token\Span;

/**
 * Default implementation of {@see SourceAwareException}: no rule, no params, the
 * message as the reason, and a span that the SQL builder may attach later.
 * Subclasses describe themselves with {@see describeSource()}.
 */
trait SourceAwareTrait
{
    private ?Span $sourceSpan = null;
    private ?string $sourceRule = null;
    /** @var array<string, mixed> */
    private array $sourceParams = [];
    private ?string $sourceReason = null;

    public function getRule(): ?string
    {
        return $this->sourceRule;
    }

    public function getSpan(): ?Span
    {
        return $this->sourceSpan;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->sourceParams;
    }

    public function getReason(): string
    {
        return $this->sourceReason ?? $this->getMessage();
    }

    public function attachSpan(Span $span): static
    {
        $this->sourceSpan ??= $span;
        return $this;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function describeSource(?string $rule, array $params = [], ?string $reason = null): void
    {
        $this->sourceRule = $rule;
        $this->sourceParams = $params;
        $this->sourceReason = $reason;
    }
}
