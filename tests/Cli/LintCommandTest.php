<?php

namespace Cli;

use FQL\Cli\Command\LintCommand;
use FQL\Cli\Output;
use PHPUnit\Framework\TestCase;

class LintCommandTest extends TestCase
{
    /**
     * @param list<string> $argv
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCmd(array $argv): array
    {
        [$stdout, $stdoutStream] = Output::memory(false);
        [$stderr, $stderrStream] = Output::memory(false);
        $code = (new LintCommand())->run($argv, $stdout, $stderr);
        rewind($stdoutStream);
        rewind($stderrStream);
        return [
            $code,
            (string) stream_get_contents($stdoutStream),
            (string) stream_get_contents($stderrStream),
        ];
    }

    public function testCleanQueryExitsZero(): void
    {
        [$code, $out, $err] = $this->runCmd(['-e', 'SELECT name FROM json(products.json)']);
        $this->assertSame(0, $code);
        $this->assertSame('', $out);
        $this->assertStringContainsString('0 errors', $err);
    }

    public function testUnknownFunctionExitsOne(): void
    {
        [$code, $out, $err] = $this->runCmd(['-e', 'SELECT LOEWR(x) FROM json(p.json)']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('unknown-function', $out);
        $this->assertStringContainsString('1 error', $err);
    }

    public function testJsonFormat(): void
    {
        [$code, $out] = $this->runCmd(['-e', 'SELECT LOEWR(x) FROM json(p.json)', '--format=json']);
        $this->assertSame(1, $code);
        $decoded = json_decode($out, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertSame('unknown-function', $decoded[0]['rule']);
        $this->assertSame('error', $decoded[0]['severity']);
    }

    public function testSeverityFilter(): void
    {
        // Missing FROM = warning; filter to errors only → no output, exit 0.
        [$code, $out, $err] = $this->runCmd(['-e', 'SELECT 1 AS one', '--severity=error']);
        $this->assertSame(0, $code);
        $this->assertSame('', trim($out));
        $this->assertStringContainsString('0 error', $err);
    }

    public function testSeverityShowsWarningsByDefault(): void
    {
        [$code, $out] = $this->runCmd(['-e', 'SELECT 1 AS one']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('missing-from', $out);
    }

    public function testFileNotFoundUsageError(): void
    {
        [$code, , $err] = $this->runCmd(['/definitely/not/a/real/path.sql']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('Cannot read', $err);
    }

    public function testInvalidSeverityUsageError(): void
    {
        [$code, , $err] = $this->runCmd(['-e', 'SELECT 1', '--severity=nope']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('severity', $err);
    }

    public function testInvalidFormatUsageError(): void
    {
        [$code, , $err] = $this->runCmd(['-e', 'SELECT 1', '--format=xml']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('format', $err);
    }

    public function testSyntaxErrorReported(): void
    {
        [$code, $out] = $this->runCmd(['-e', 'SELECT name FROM WHERE']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('syntax-error', $out);
    }

    public function testCheckFsFlag(): void
    {
        [$code, $out] = $this->runCmd(['-e', 'SELECT * FROM csv(/tmp/never-exists.csv)', '--check-fs']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('file-not-found', $out);
    }

    public function testCheckFsOffSkipsFilesystem(): void
    {
        [$code] = $this->runCmd(['-e', 'SELECT * FROM csv(/tmp/never-exists.csv)']);
        $this->assertSame(0, $code);
    }

    public function testFileInput(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fqlint');
        file_put_contents($tmp, 'SELECT LOEWR(x) FROM json(p.json)');
        try {
            [$code, $out] = $this->runCmd([$tmp]);
            $this->assertSame(1, $code);
            $this->assertStringContainsString($tmp, $out);
            $this->assertStringContainsString('unknown-function', $out);
        } finally {
            unlink($tmp);
        }
    }

    public function testUsageShape(): void
    {
        $usage = (new LintCommand())->usage();
        $this->assertStringContainsString('Usage: fql-dev lint', $usage);
        $this->assertStringContainsString('--severity', $usage);
        $this->assertStringContainsString('--format', $usage);
    }

    public function testName(): void
    {
        $this->assertSame('lint', (new LintCommand())->name());
    }
}
