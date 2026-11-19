<?php

namespace FQL\Exception;

use FQL\Sql\Token\Span;

/**
 * An exception that can point into the FQL text it is about. Every FiQueLa exception
 * implements it, so tooling (editors, CLIs, APIs) can catch one type and read a
 * structured position and parameters instead of parsing the message:
 *
 *  - {@see getRule()}   – stable id of the error kind (`syntax-error`, `unknown-function`,
 *                         `file-not-found`, `file-outside-base`, `file-exists`,
 *                         `file-unreadable`), the same ids lint uses; null for errors
 *                         without a dedicated id;
 *  - {@see getSpan()}   – byte range `[start, end)` in the SQL string that was parsed;
 *                         null when the error is not tied to the text (fluent API,
 *                         data errors during execution);
 *  - {@see getParams()} – values for translating the error by rule, e.g.
 *                         `['function' => 'FOO']` or `['path' => './data.csv']`;
 *  - {@see getReason()} – the English message without the position suffix
 *                         (`getMessage()` stays as it always was).
 */
interface SourceAwareException extends \Throwable
{
    public function getRule(): ?string;

    public function getSpan(): ?Span;

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array;

    public function getReason(): string;

    /**
     * Sets the span unless the exception already has one and returns the same
     * exception. Used by the SQL builder to tie an error raised while compiling a
     * node to that node's text; an inner, more specific span is never replaced.
     */
    public function attachSpan(Span $span): static;
}
