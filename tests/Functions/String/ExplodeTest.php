<?php

namespace Functions\String;

use FQL\Exception\UnexpectedValueException;
use FQL\Functions\String\Explode;
use PHPUnit\Framework\TestCase;

class ExplodeTest extends TestCase
{
    public function testExplodeDefaultSeparator(): void
    {
        $explode = new Explode('value');
        $this->assertSame(['a', 'b', 'c'], $explode(['value' => 'a,b,c'], []));
    }

    public function testExplodeCustomSeparator(): void
    {
        $explode = new Explode('value', ';');
        $this->assertSame(['a', 'b', 'c'], $explode(['value' => 'a;b;c'], []));
    }

    public function testExplodeEmptySeparator(): void
    {
        $explode = new Explode('value', '');
        $this->assertSame(['a', 'b', 'c'], $explode(['value' => 'abc'], []));
    }

    public function testExplodeInvalidValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $explode = new Explode('value');
        $explode(['value' => ['a', 'b']], []);
    }

    public function testToString(): void
    {
        $explode = new Explode('value');
        $this->assertSame('EXPLODE(value, ",")', (string) $explode);
    }
}
