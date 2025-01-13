<?php

namespace FQL\Stream;

use FQL\Query\Provider;
use FQL\Query\Query;
use FQL\Results\Results;

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
    public function provideSource(): string;

    public function query(): Query;

    public function sql(string $sql): Results;
}
