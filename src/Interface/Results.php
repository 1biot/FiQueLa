<?php

namespace FQL\Interface;

use FQL\Results\Stream;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from Stream
 */
interface Results extends \Countable
{
    /**
     * @template T of mixed
     * @param ?class-string $dto
     * @return ($dto is class-string<T> ? \Generator<T> : \Generator<StreamProviderArrayIteratorValue>)
     */
    public function fetchAll(?string $dto = null): \Generator;

    /**
     * @template T of mixed
     * @param ?class-string $dto
     * @return ($dto is class-string<T> ? T : StreamProviderArrayIteratorValue)
     */
    public function fetch(?string $dto = null): mixed;

    /**
     * @return StreamProviderArrayIteratorValue
     */
    public function fetchSingle(string $key): mixed;

    /**
     * @template T of mixed
     * @param ?class-string $dto
     * @return ($dto is class-string<T> ? \Generator<T> : \Generator<StreamProviderArrayIteratorValue>)
     */
    public function fetchNth(int|string $n, ?string $dto = null): \Generator;

    public function exists(): bool;

    public function sum(string $key): float;

    public function avg(string $key, int $decimalPlaces = 2): float;

    public function min(string $key): float;

    public function max(string $key): float;
}
