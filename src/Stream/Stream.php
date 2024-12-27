<?php

namespace UQL\Stream;

use UQL\Query\Query;

/**
 * @template T
 */
interface Stream
{
    /** @return self<T> */
    public static function open(string $path): self;

    /** @return self<T> */
    public static function string(string $data): self;

    /** @return T */
    public function getStream(?string $query): mixed;

    public function query(): Query;

    public function sql(string $sql): \Generator;
}
