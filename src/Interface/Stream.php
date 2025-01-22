<?php

namespace FQL\Interface;

use FQL\Stream\ArrayStreamProvider;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 */
interface Stream
{
    public static function open(string $path): self;

    public static function string(string $data): self;

    /**
     * @param string|null $query
     * @return StreamProviderArrayIterator
     */
    public function getStream(?string $query): \ArrayIterator;
    public function getStreamGenerator(?string $query): \Generator;
    public function provideSource(): string;

    public function query(): Query;

    public function fql(string $sql): Results;
}
