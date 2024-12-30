<?php

namespace UQL\Stream;

use UQL\Query\Query;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 * @template T
 */
interface Stream
{
    /** @return self<T> */
    public static function open(string $path): self;

    /** @return self<T> */
    public static function string(string $data): self;

    /**
     * @param string|null $query
     * @return StreamProviderArrayIterator|null
     */
    public function getStream(?string $query): ?\ArrayIterator;
    public function getStreamGenerator(?string $query): ?\Generator;

    public function query(): Query;

    public function sql(string $sql): \Generator;
}
