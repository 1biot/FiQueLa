<?php

namespace FQL\Functions\Utils;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\Core\ScalarFunction;

/**
 * Generates cryptographically secure random bytes.
 *
 * Suitable for generating salts, keys, and nonces.
 */
final class RandomBytes implements ScalarFunction
{
    public static function name(): string
    {
        return 'RANDOM_BYTES';
    }

    /**
     * @throws UnexpectedValueException
     */
    public static function execute(int $length = 10): string
    {
        try {
            return random_bytes(max(1, $length));
        } catch (\Exception $e) {
            throw new UnexpectedValueException('Failed to generate random bytes', previous: $e);
        }
    }
}
