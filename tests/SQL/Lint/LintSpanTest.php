<?php

namespace SQL\Lint;

use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\Linter;
use FQL\Sql\Lint\LintReport;
use FQL\Sql\Lint\Rule\FileOutsideBaseRule;
use FQL\Sql\Lint\Severity;
use FQL\Sql\Provider;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;
use PHPUnit\Framework\TestCase;

/**
 * Lint issues carry the span of the offending token or node and the params their
 * message is built from; file rules resolve paths against the base path.
 */
class LintSpanTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/fql-lint-span-' . uniqid();
        mkdir($this->dir . '/project/data', 0775, true);
        file_put_contents($this->dir . '/project/data/a.json', '[]');
        file_put_contents($this->dir . '/outside.json', '[]');
    }

    protected function tearDown(): void
    {
        foreach (['/project/data/a.json', '/outside.json'] as $file) {
            @unlink($this->dir . $file);
        }
        @rmdir($this->dir . '/project/data');
        @rmdir($this->dir . '/project');
        @rmdir($this->dir);
    }

    public function testUnknownFunctionSpansTheName(): void
    {
        $sql = 'SELECT name, loewr(name) AS n FROM json(a.json)';
        $issue = Provider::lint($sql)->issues[0];

        $this->assertSame('unknown-function', $issue->rule);
        $this->assertSame('loewr', $issue->span?->slice($sql));
        $this->assertSame(['function' => 'LOEWR'], $issue->params);
        $this->assertSame(13, $issue->position?->offset);
    }

    public function testDuplicateAliasSpansTheAlias(): void
    {
        $sql = "SELECT a AS x,\n  b AS x, c AS `x` FROM json(a.json)";
        $issues = Provider::lint($sql)->issues;

        $this->assertCount(2, $issues);
        $this->assertSame(['x', '`x`'], array_map(fn (LintIssue $i): ?string => $i->span?->slice($sql), $issues));
        $this->assertSame(['alias' => 'x'], $issues[0]->params);
        // The position keeps pointing at the start of the field.
        $this->assertSame(17, $issues[0]->position?->offset);
    }

    public function testSyntaxErrorSpansTheTokenAndCarriesTheParseParams(): void
    {
        $sql = 'SELECT a FROM x WHERE a = "abc';
        $issue = Provider::lint($sql)->issues[0];

        $this->assertSame('syntax-error', $issue->rule);
        $this->assertSame('"', $issue->span?->slice($sql));
        $this->assertSame('unterminated-string', $issue->params['kind']);
        $this->assertSame('Unterminated string literal', $issue->params['reason']);
        $this->assertSame('Unterminated string literal starting at line 1, column 27', $issue->message);
    }

    public function testMissingFromHasNoSpan(): void
    {
        $issue = Provider::lint('SELECT 1')->issues[0];

        $this->assertSame('missing-from', $issue->rule);
        $this->assertNull($issue->span);
        $this->assertSame([], $issue->params);
    }

    public function testFileNotFoundSpansTheFileQuery(): void
    {
        $sql = 'SELECT * FROM json(./nope.json).data';
        $issue = Provider::lint($sql, checkFilesystem: true)->issues[0];

        $this->assertSame('file-not-found', $issue->rule);
        $this->assertSame('json(./nope.json).data', $issue->span?->slice($sql));
        $this->assertSame(['path' => './nope.json'], $issue->params);
    }

    public function testIntoTargetIsNotReportedAsMissing(): void
    {
        $this->assertCount(
            0,
            Provider::lint('SELECT * FROM json(composer.json) INTO json(./not-there-yet.json)', checkFilesystem: true)
        );
    }

    public function testFilesResolveAgainstTheBasePath(): void
    {
        $base = $this->dir . '/project';

        $this->assertCount(0, Provider::lint('SELECT * FROM json(data/a.json)', true, $base));
        $this->assertCount(0, Provider::lint('SELECT * FROM json(./data/../data/a.json)', true, $base));

        $sql = 'SELECT * FROM json(composer.json)';
        $this->assertCount(0, Provider::lint($sql, true));
        $issues = Provider::lint($sql, true, $base)->issues;
        $this->assertSame(['file-not-found'], array_map(fn (LintIssue $i): string => $i->rule, $issues));
    }

    public function testFilesOutsideTheBasePath(): void
    {
        $base = $this->dir . '/project';
        $sql = 'SELECT * FROM json(../outside.json) JOIN json(../../missing.json) AS m ON id = m.id'
            . ' INTO json(../out.json)';

        $issues = Provider::lint($sql, true, $base)->issues;

        $this->assertSame(
            [
                ['file-outside-base', 'json(../outside.json)', '../outside.json'],
                ['file-outside-base', 'json(../../missing.json)', '../../missing.json'],
                ['file-outside-base', 'json(../out.json)', '../out.json'],
            ],
            array_map(fn (LintIssue $i): array => [$i->rule, $i->span?->slice($sql), $i->params['path']], $issues)
        );
        $this->assertSame($base, $issues[0]->params['basePath']);

        // Without a base path nothing is outside it (both sources are missing relative to the working directory).
        $this->assertSame(
            ['file-not-found', 'file-not-found'],
            array_map(fn (LintIssue $i): string => $i->rule, Provider::lint($sql, true)->issues)
        );
    }

    public function testFileRulesStayOffWithoutTheFilesystemFlag(): void
    {
        $this->assertCount(0, Provider::lint('SELECT * FROM json(../nope.json)', false, $this->dir . '/project'));
    }

    public function testDefaultRulesIncludeTheBasePathRule(): void
    {
        $this->assertContainsOnlyInstancesOf(\FQL\Sql\Lint\Rule::class, Linter::defaultRules());
        $this->assertCount(
            1,
            array_filter(Linter::defaultRules(), fn ($rule): bool => $rule instanceof FileOutsideBaseRule)
        );
    }

    public function testToArrayIncludesSpanAndParams(): void
    {
        $issue = new LintIssue(
            Severity::ERROR,
            'unknown-function',
            'Unknown function "X".',
            new Position(7, 1, 8),
            new Span(7, 8),
            ['function' => 'X']
        );

        $array = $issue->toArray();
        $this->assertSame(['start' => 7, 'end' => 8], $array['span']);
        $this->assertSame(['function' => 'X'], $array['params']);
        $this->assertSame(7, $array['offset']);

        $plain = (new LintIssue(Severity::WARNING, 'missing-from', 'No FROM'))->toArray();
        $this->assertNull($plain['span']);
        $this->assertSame([], $plain['params']);
        $this->assertSame([$array], (new LintReport([$issue]))->toArray());
    }
}
