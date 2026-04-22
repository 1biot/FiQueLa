<?php

namespace Cli;

use FQL\Cli\Output;
use PHPUnit\Framework\TestCase;

class OutputTest extends TestCase
{
    public function testColorOff(): void
    {
        [$out, ] = Output::memory(false);
        $this->assertSame('hello', $out->red('hello'));
        $this->assertSame('hello', $out->bold('hello'));
        $this->assertSame('hello', $out->cyan('hello'));
    }

    public function testColorOn(): void
    {
        [$out, ] = Output::memory(true);
        $this->assertSame("\033[31mhello\033[0m", $out->red('hello'));
        $this->assertSame("\033[1mhello\033[0m", $out->bold('hello'));
        $this->assertSame("\033[2mhello\033[0m", $out->dim('hello'));
        $this->assertSame("\033[36mhello\033[0m", $out->cyan('hello'));
        $this->assertSame("\033[33mhello\033[0m", $out->yellow('hello'));
    }

    public function testWriteAndWriteln(): void
    {
        [$out, $stream] = Output::memory(false);
        $out->write('foo');
        $out->writeln('bar');
        $out->writeln();
        rewind($stream);
        $this->assertSame('foobar' . PHP_EOL . PHP_EOL, stream_get_contents($stream));
    }

    public function testForStdoutWithOverride(): void
    {
        $out = Output::forStdout(true);
        $this->assertTrue($out->useColor);
        $out2 = Output::forStdout(false);
        $this->assertFalse($out2->useColor);
    }

    public function testForStderrWithOverride(): void
    {
        $out = Output::forStderr(true);
        $this->assertTrue($out->useColor);
    }
}
