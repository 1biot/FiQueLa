<?php

namespace Cli;

use FQL\Cli\Command\FormatCommand;
use FQL\Cli\Output;
use PHPUnit\Framework\TestCase;

class FormatCommandTest extends TestCase
{
    /**
     * @param list<string> $argv
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCmd(array $argv): array
    {
        [$stdout, $stdoutStream] = Output::memory(false);
        [$stderr, $stderrStream] = Output::memory(false);
        $code = (new FormatCommand())->run($argv, $stdout, $stderr);
        rewind($stdoutStream);
        rewind($stderrStream);
        return [$code, (string) stream_get_contents($stdoutStream), (string) stream_get_contents($stderrStream)];
    }

    public function testFormatsInline(): void
    {
        [$code, $out] = $this->runCmd(['-e', 'SELECT a,b FROM json(x.json)']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('SELECT', $out);
        $this->assertStringContainsString('FROM json(x.json)', $out);
    }

    public function testParseErrorExitsOne(): void
    {
        [$code, , $err] = $this->runCmd(['-e', 'SELECT name FROM WHERE']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Parse error', $err);
    }

    public function testMissingInput(): void
    {
        // With STDIN attached to a TTY (test runner), no positional and no -e → usage error.
        // In CI, STDIN may be non-TTY → read from stdin and get empty string which produces parse error.
        // Just assert exit code is non-zero either way.
        [$code] = $this->runCmd([]);
        $this->assertNotSame(0, $code);
    }

    public function testName(): void
    {
        $this->assertSame('format', (new FormatCommand())->name());
    }

    public function testInvalidIndent(): void
    {
        [$code, , $err] = $this->runCmd(['-e', 'SELECT 1', '--indent=abc']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('indent', $err);
    }
}
