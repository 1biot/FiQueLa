<?php

namespace Cli;

use FQL\Cli\Application;
use FQL\Cli\Output;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    /**
     * @param list<string> $argv
     * @return array{0: int, 1: string, 2: string}
     */
    private function runApp(array $argv): array
    {
        [$stdout, $stdoutStream] = Output::memory(false);
        [$stderr, $stderrStream] = Output::memory(false);
        $code = (new Application())->run(['bin/fql-dev', ...$argv], $stdout, $stderr);
        rewind($stdoutStream);
        rewind($stderrStream);
        return [$code, (string) stream_get_contents($stdoutStream), (string) stream_get_contents($stderrStream)];
    }

    public function testBareInvocationShowsHelp(): void
    {
        [$code, $out] = $this->runApp([]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Available commands', $out);
    }

    public function testGlobalHelp(): void
    {
        [$code, $out] = $this->runApp(['--help']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Available commands', $out);
    }

    public function testVersion(): void
    {
        [$code, $out] = $this->runApp(['--version']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('fql-dev', $out);
    }

    public function testVersionShort(): void
    {
        [$code, $out] = $this->runApp(['-V']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('fql-dev', $out);
    }

    public function testUnknownCommandExitsTwo(): void
    {
        [$code, , $err] = $this->runApp(['nonsense']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('Unknown command', $err);
    }

    public function testLintCommandDispatch(): void
    {
        [$code, $out] = $this->runApp(['lint', '-e', 'SELECT LOEWR(x) FROM json(p.json)']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('unknown-function', $out);
    }

    public function testFormatCommandDispatch(): void
    {
        [$code, $out] = $this->runApp(['format', '-e', 'SELECT a FROM json(x.json)']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('SELECT', $out);
    }

    public function testHighlightCommandDispatch(): void
    {
        [$code, $out] = $this->runApp(['highlight', '-e', 'SELECT a FROM json(x.json)', '--format=html']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('<span', $out);
    }

    public function testCommandHelpShortcut(): void
    {
        [$code, $out] = $this->runApp(['lint', '--help']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Usage: fql-dev lint', $out);
    }

    public function testHelpForCommand(): void
    {
        [$code, $out] = $this->runApp(['help', 'format']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Usage: fql-dev format', $out);
    }
}
