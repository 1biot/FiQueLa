<?php

namespace Cli;

use FQL\Cli\Command\HighlightCommand;
use FQL\Cli\Output;
use PHPUnit\Framework\TestCase;

class HighlightCommandTest extends TestCase
{
    /**
     * @param list<string> $argv
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCmd(array $argv): array
    {
        [$stdout, $stdoutStream] = Output::memory(false);
        [$stderr, $stderrStream] = Output::memory(false);
        $code = (new HighlightCommand())->run($argv, $stdout, $stderr);
        rewind($stdoutStream);
        rewind($stderrStream);
        return [$code, (string) stream_get_contents($stdoutStream), (string) stream_get_contents($stderrStream)];
    }

    public function testBashFormatDefault(): void
    {
        [$code, $out] = $this->runCmd(['-e', 'SELECT a FROM json(x.json)']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString("\033[", $out);   // ANSI escape present
    }

    public function testHtmlFormat(): void
    {
        [$code, $out] = $this->runCmd(['-e', 'SELECT a FROM json(x.json)', '--format=html']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('<span class="fql-', $out);
        $this->assertStringContainsString('</span>', $out);
    }

    public function testUnknownFormat(): void
    {
        [$code, , $err] = $this->runCmd(['-e', 'SELECT a', '--format=markdown']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('format', $err);
    }

    public function testName(): void
    {
        $this->assertSame('highlight', (new HighlightCommand())->name());
    }
}
