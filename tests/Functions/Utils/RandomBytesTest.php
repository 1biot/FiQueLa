<?php

namespace Functions\Utils;

use FQL\Functions\Utils\RandomBytes;
use PHPUnit\Framework\TestCase;

class RandomBytesTest extends TestCase
{
    public function testRandomBytesDefault(): void
    {
        $randomBytes = new RandomBytes();
        $this->assertEquals(
            10,
            strlen($randomBytes())
        );
    }

    public function testRandomBytes(): void
    {
        $randomBytes = new RandomBytes(16);
        $this->assertEquals(
            16,
            strlen($randomBytes())
        );
    }

    public function testToStringIncludesLength(): void
    {
        $randomBytes = new RandomBytes(4);

        $this->assertSame('RANDOM_BYTES(4)', (string) $randomBytes);
    }
}
