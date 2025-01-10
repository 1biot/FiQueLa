<?php

namespace UQL\Functions\Utils;

use UQL\Exceptions\UnexpectedValueException;
use UQL\Functions\Core\NoFieldFunction;

/**
 * Generates cryptographically secure random bytes.
 *
 * Suitable for generating salts, keys, and nonces.
 */
class RandomBytes extends NoFieldFunction
{
    public function __construct(private readonly int $length = 10)
    {
    }

    /**
     * @return string
     * @throws UnexpectedValueException
     */
    public function __invoke(): mixed
    {
        try {
            return random_bytes($this->length);
        } catch (\Exception $e) {
            throw new UnexpectedValueException('Failed to generate random bytes', previous: $e);
        }
    }

    /**
     * @throws UnexpectedValueException
     */
    public function __toString(): string
    {
        return sprintf('%s(%s)', $this->getName(), $this->length);
    }
}
