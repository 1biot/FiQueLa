<?php

namespace FQL\Interface;

use FQL\Results\Stream;

/**
 * @phpstan-import-type StreamProviderArrayIteratorValue from Stream
 */
interface Results extends \Countable
{
    /**
     * @param class-string|null $dto
     * @return \Generator<StreamProviderArrayIteratorValue|object>
     */
    public function fetchAll(?string $dto = null): \Generator;

    /**
     * @param class-string|null $dto
     * @return StreamProviderArrayIteratorValue|object|null
     */
    public function fetch(?string $dto = null): mixed;

    /**
     * @return StreamProviderArrayIteratorValue|null
     */
    public function fetchSingle(string $key): mixed;

    /**
     * @param class-string|null $dto
     * @return \Generator<StreamProviderArrayIteratorValue|object>
     */
    public function fetchNth(int|string $n, ?string $dto = null): \Generator;

    public function exists(): bool;

    public function sum(string $key): float;

    public function avg(string $key, int $decimalPlaces = 2): float;

    public function min(string $key): float;

    public function max(string $key): float;
}
