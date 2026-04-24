<?php

namespace FQL\Functions\String;

use FQL\Functions\Core\ScalarFunction;

final class RandomString implements ScalarFunction
{
    private const DEFAULT_CHARSET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public static function name(): string
    {
        return 'RANDOM_STRING';
    }

    public static function execute(int $length = 10): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be greater than 0.');
        }

        $charsetLength = strlen(self::DEFAULT_CHARSET);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomIndex = random_int(0, $charsetLength - 1); // Securely selects a random index.
            $randomString .= self::DEFAULT_CHARSET[$randomIndex];
        }

        return $randomString;
    }
}
