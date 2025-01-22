<?php

namespace FQL\Interface;

interface Results extends \Countable
{
    /** @param ?class-string $dto */
    public function fetchAll(?string $dto = null): \Generator;
    /** @param ?class-string $dto */
    public function fetch(?string $dto = null): mixed;
    public function fetchSingle(string $key): mixed;
    /** @param ?class-string $dto */
    public function fetchNth(int|string $n, ?string $dto = null): \Generator;
    public function exists(): bool;

    public function sum(string $key): float;
    public function avg(string $key, int $decimalPlaces = 2): float;
    public function min(string $key): float;
    public function max(string $key): float;
}
