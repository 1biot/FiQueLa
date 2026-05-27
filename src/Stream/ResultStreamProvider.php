<?php

namespace FQL\Stream;

use FQL\Exception\InvalidFormatException;

/**
 * In-memory stream backing — usually wraps results captured from another Query.
 *
 * The optional `$sourceLabel` constructor argument lets callers tag the provider
 * with a human-readable origin (e.g. a CTE name) so {@see provideSource()} —
 * and thus {@see \FQL\Query\Query::__toString()} — produces a meaningful FROM
 * clause instead of the default `results(memory)` placeholder. Reparsing a
 * Query whose source label is a bare identifier requires a matching `WITH`
 * clause in scope, which {@see \FQL\Traits\Withable} prepends automatically.
 *
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
class ResultStreamProvider extends ArrayStreamProvider
{
    /**
     * @param StreamProviderArrayIterator $stream
     */
    public function __construct(\ArrayIterator $stream, private readonly ?string $sourceLabel = null)
    {
        parent::__construct($stream);
    }

    /**
     * @throws InvalidFormatException
     */
    public static function open(string $path): \FQL\Interface\Stream
    {
        throw new \FQL\Exception\InvalidFormatException('ResultStreamProvider does not support open()');
    }

    /**
     * @throws InvalidFormatException
     */
    public static function string(string $data): \FQL\Interface\Stream
    {
        throw new \FQL\Exception\InvalidFormatException('ResultStreamProvider does not support string()');
    }

    public function provideSource(): string
    {
        return $this->sourceLabel ?? 'results(memory)';
    }
}
