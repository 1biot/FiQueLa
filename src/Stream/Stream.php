<?php

namespace UQL\Stream;

use UQL\Query\Query;

interface Stream
{
    public static function open(string $path): self;
    public static function string(string $data): self;
    public function query(): Query;
    public function sql(string $sql): \Generator;
}
