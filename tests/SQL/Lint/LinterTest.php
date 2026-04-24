<?php

namespace SQL\Lint;

use FQL\Sql\Lint\Linter;
use FQL\Sql\Lint\LintReport;
use FQL\Sql\Lint\Severity;
use FQL\Sql\Provider;
use PHPUnit\Framework\TestCase;

class LinterTest extends TestCase
{
    public function testCleanQueryProducesEmptyReport(): void
    {
        $report = Provider::lint('SELECT name, COUNT(*) FROM json(examples/data/products.json) GROUP BY category');
        $this->assertInstanceOf(LintReport::class, $report);
        $this->assertCount(0, $report);
        $this->assertFalse($report->hasErrors());
        $this->assertFalse($report->hasWarnings());
    }

    public function testSyntaxErrorReturnsSingleIssue(): void
    {
        $report = Provider::lint('SELECT name FROM WHERE');
        $this->assertCount(1, $report);
        $issue = $report->issues[0];
        $this->assertSame(Severity::ERROR, $issue->severity);
        $this->assertSame('syntax-error', $issue->rule);
        $this->assertNotNull($issue->position);
    }

    public function testUnknownFunction(): void
    {
        $report = Provider::lint('SELECT LOEWR(name) FROM json(products.json)');
        $this->assertCount(1, $report);
        $this->assertSame('unknown-function', $report->issues[0]->rule);
        $this->assertStringContainsString('LOEWR', $report->issues[0]->message);
    }

    public function testKnownFunctionIsClean(): void
    {
        $report = Provider::lint('SELECT LOWER(name) FROM json(products.json)');
        $this->assertCount(0, $report);
    }

    public function testAggregateFunctionIsKnown(): void
    {
        $report = Provider::lint('SELECT COUNT(*), SUM(price) FROM json(products.json)');
        $this->assertCount(0, $report);
    }

    public function testDuplicateAlias(): void
    {
        $report = Provider::lint('SELECT a AS x, b AS x FROM json(products.json)');
        $this->assertCount(1, $report);
        $issue = $report->issues[0];
        $this->assertSame('duplicate-alias', $issue->rule);
        $this->assertSame(Severity::ERROR, $issue->severity);
        $this->assertStringContainsString('"x"', $issue->message);
    }

    public function testDistinctAliasesClean(): void
    {
        $report = Provider::lint('SELECT a AS x, b AS y FROM json(products.json)');
        $this->assertCount(0, $report);
    }

    public function testMissingFromIsWarning(): void
    {
        $report = Provider::lint('SELECT 1 AS one');
        $this->assertCount(1, $report);
        $issue = $report->issues[0];
        $this->assertSame('missing-from', $issue->rule);
        $this->assertSame(Severity::WARNING, $issue->severity);
        $this->assertNull($issue->position);
        $this->assertFalse($report->hasErrors());
        $this->assertTrue($report->hasWarnings());
    }

    public function testFileNotFoundSkippedByDefault(): void
    {
        $report = Provider::lint('SELECT * FROM csv(/tmp/definitely-does-not-exist.csv)');
        $this->assertCount(0, $report);
    }

    public function testFileNotFoundReportedWhenEnabled(): void
    {
        $report = Provider::lint('SELECT * FROM csv(/tmp/definitely-does-not-exist.csv)', true);
        $this->assertCount(1, $report);
        $this->assertSame('file-not-found', $report->issues[0]->rule);
        $this->assertSame(Severity::ERROR, $report->issues[0]->severity);
    }

    public function testFileFoundIsClean(): void
    {
        $path = realpath(__DIR__ . '/../../../examples/data/products.json');
        $this->assertNotFalse($path);
        $report = Provider::lint(sprintf('SELECT * FROM json(%s)', $path), true);
        $this->assertCount(0, $report);
    }

    public function testMultipleIssuesSortedByPosition(): void
    {
        $report = Provider::lint('SELECT LOEWR(a) AS x, UPER(b) AS x FROM json(products.json)');
        $this->assertGreaterThanOrEqual(2, count($report));

        $issues = $report->issues;
        $positioned = array_filter($issues, static fn ($i) => $i->position !== null);
        $offsets = array_map(static fn ($i) => $i->position->offset, $positioned);
        $sortedOffsets = $offsets;
        sort($sortedOffsets);
        $this->assertSame($sortedOffsets, array_values($offsets));
    }

    public function testReportFilterBySeverity(): void
    {
        $report = Provider::lint('SELECT LOEWR(a) FROM json(products.json)');
        $errorsOnly = $report->filterBySeverity(Severity::ERROR);
        $this->assertCount(1, $errorsOnly);

        $report2 = Provider::lint('SELECT 1 AS one');
        $errorsOnly2 = $report2->filterBySeverity(Severity::ERROR);
        $this->assertCount(0, $errorsOnly2);
        $warningsOrWorse = $report2->filterBySeverity(Severity::WARNING);
        $this->assertCount(1, $warningsOrWorse);
    }

    public function testIssueToArray(): void
    {
        $report = Provider::lint('SELECT LOEWR(a) FROM json(products.json)');
        $issue = $report->issues[0];
        $arr = $issue->toArray();
        $this->assertSame('error', $arr['severity']);
        $this->assertSame('unknown-function', $arr['rule']);
        $this->assertIsInt($arr['line']);
        $this->assertIsInt($arr['column']);
    }

    public function testReportToArray(): void
    {
        $report = Provider::lint('SELECT LOEWR(a) FROM json(products.json)');
        $arr = $report->toArray();
        $this->assertCount(1, $arr);
        $this->assertSame('unknown-function', $arr[0]['rule']);
    }

    public function testSeverityIsAtLeast(): void
    {
        $this->assertTrue(Severity::ERROR->isAtLeast(Severity::WARNING));
        $this->assertTrue(Severity::ERROR->isAtLeast(Severity::ERROR));
        $this->assertFalse(Severity::WARNING->isAtLeast(Severity::ERROR));
        $this->assertTrue(Severity::INFO->isAtLeast(Severity::INFO));
    }

    public function testCustomRuleSet(): void
    {
        // Linter with only missing-from rule — unknown function is not flagged.
        $linter = new Linter([new \FQL\Sql\Lint\Rule\MissingFromRule()]);
        $report = $linter->lint('SELECT LOEWR(a) FROM json(products.json)');
        $this->assertCount(0, $report);
    }
}
