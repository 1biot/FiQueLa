<?php

namespace UQL\Functions\String;

use UQL\Exceptions\UnexpectedValueException;
use UQL\Functions\Core\NoFieldFunction;

class RandomString extends NoFieldFunction
{
    private const DEFAULT_CHARSET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function __construct(
        private readonly int $length = 10
    ) {
    }

    public function __invoke(): mixed
    {
        if ($this->length < 1) {
            throw new \InvalidArgumentException('Length must be greater than 0.');
        }

        $charsetLength = strlen(self::DEFAULT_CHARSET);
        $randomString = '';
        for ($i = 0; $i < $this->length; $i++) {
            $randomIndex = random_int(0, $charsetLength - 1); // Securely selects a random index.
            $randomString .= self::DEFAULT_CHARSET[$randomIndex];
        }

        return $randomString;
    }

    /**
     * @throws UnexpectedValueException
     */
    public function __toString(): string
    {
        return sprintf('%s(%s)', $this->getName(), $this->length);
    }
}
