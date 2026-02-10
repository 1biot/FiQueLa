<?php

namespace Functions\Core;

use FQL\Functions\Core\NoFieldFunction;
use PHPUnit\Framework\TestCase;

class NoFieldFunctionTest extends TestCase
{
    public function testGetNameUsesClassName(): void
    {
        $function = new TestNoFieldNameFunction();

        $this->assertSame('TEST_NO_FIELD_NAME_FUNCTION', $function->getName());
    }
}

final class TestNoFieldNameFunction extends NoFieldFunction
{
    public function __toString(): string
    {
        return $this->getName();
    }

    public function __invoke(): mixed
    {
        return null;
    }
}
