<?php

namespace FQL\Stream;

use FQL\Exception\InvalidFormatException;

class ResultStreamProvider extends ArrayStreamProvider
{
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
        return '[results](memory)';
    }
}
