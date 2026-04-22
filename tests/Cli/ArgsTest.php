<?php

namespace Cli;

use FQL\Cli\Args;
use PHPUnit\Framework\TestCase;

class ArgsTest extends TestCase
{
    public function testEmpty(): void
    {
        $a = Args::parse([]);
        $this->assertSame([], $a->positional);
        $this->assertSame([], $a->options);
        $this->assertNull($a->first());
    }

    public function testPositionalOnly(): void
    {
        $a = Args::parse(['file.sql', 'other.sql']);
        $this->assertSame(['file.sql', 'other.sql'], $a->positional);
        $this->assertSame('file.sql', $a->first());
    }

    public function testLongFlag(): void
    {
        $a = Args::parse(['--verbose']);
        $this->assertTrue($a->bool('verbose'));
    }

    public function testLongOptionWithValue(): void
    {
        // --severity must be listed as value-taking; otherwise `error`
        // falls through to positional.
        $a = Args::parse(['--severity', 'error'], valueLongOptions: ['severity']);
        $this->assertSame('error', $a->string('severity'));
        $this->assertSame([], $a->positional);
    }

    public function testUnregisteredLongFlagLeavesPositional(): void
    {
        // Without registering 'severity' as value-taking, it is a bare flag
        // and 'error' becomes positional — deterministic parsing.
        $a = Args::parse(['--severity', 'error']);
        $this->assertTrue($a->bool('severity'));
        $this->assertSame(['error'], $a->positional);
    }

    public function testLongOptionWithEquals(): void
    {
        $a = Args::parse(['--severity=warning', 'file.sql']);
        $this->assertSame('warning', $a->string('severity'));
        $this->assertSame(['file.sql'], $a->positional);
    }

    public function testNegatedLongFlag(): void
    {
        $a = Args::parse(['--no-color']);
        $this->assertFalse($a->bool('color', true));
    }

    public function testShortFlagBool(): void
    {
        $a = Args::parse(['-h']);
        $this->assertTrue($a->bool('h'));
    }

    public function testShortOptionWithValue(): void
    {
        // -e is listed as value-short-flag by default
        $a = Args::parse(['-e', 'SELECT 1']);
        $this->assertSame('SELECT 1', $a->string('e'));
        $this->assertSame([], $a->positional);
    }

    public function testShortOptionGluedValue(): void
    {
        $a = Args::parse(['-eSELECT 1']);
        $this->assertSame('SELECT 1', $a->string('e'));
    }

    public function testBareDashKeptAsPositional(): void
    {
        $a = Args::parse(['-']);
        $this->assertSame(['-'], $a->positional);
    }

    public function testDoubleDashForcesPositional(): void
    {
        $a = Args::parse(['lint', '--', '--flag', '-e']);
        $this->assertSame(['lint', '--flag', '-e'], $a->positional);
        $this->assertSame([], $a->options);
    }

    public function testMixedOrder(): void
    {
        $a = Args::parse(['--severity=error', 'file.sql', '--check-fs']);
        $this->assertSame(['file.sql'], $a->positional);
        $this->assertSame('error', $a->string('severity'));
        $this->assertTrue($a->bool('check-fs'));
    }

    public function testBoolFromString(): void
    {
        $a = Args::parse(['--debug=0']);
        $this->assertFalse($a->bool('debug'));

        $a2 = Args::parse(['--debug=true']);
        $this->assertTrue($a2->bool('debug'));
    }

    public function testStringDefaultWhenMissing(): void
    {
        $a = Args::parse([]);
        $this->assertSame('default', $a->string('nope', 'default'));
        $this->assertNull($a->string('nope'));
    }

    public function testHasVsDefault(): void
    {
        $a = Args::parse(['--verbose']);
        $this->assertTrue($a->has('verbose'));
        $this->assertFalse($a->has('missing'));
    }

    public function testFirstPositional(): void
    {
        $a = Args::parse(['--flag', 'foo', 'bar']);
        $this->assertSame('foo', $a->first());
    }
}
