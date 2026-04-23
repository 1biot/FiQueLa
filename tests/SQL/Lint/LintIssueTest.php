<?php

namespace SQL\Lint;

use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\Severity;
use FQL\Sql\Token\Position;
use PHPUnit\Framework\TestCase;

class LintIssueTest extends TestCase
{
    public function testToArrayWithPosition(): void
    {
        $issue = new LintIssue(
            Severity::ERROR,
            'unknown-function',
            'Unknown function "X".',
            new Position(10, 2, 5)
        );

        $arr = $issue->toArray();
        $this->assertSame('error', $arr['severity']);
        $this->assertSame('unknown-function', $arr['rule']);
        $this->assertSame(2, $arr['line']);
        $this->assertSame(5, $arr['column']);
        $this->assertSame(10, $arr['offset']);
    }

    public function testToArrayWithoutPosition(): void
    {
        $issue = new LintIssue(Severity::WARNING, 'missing-from', 'No FROM', null);
        $arr = $issue->toArray();
        $this->assertNull($arr['line']);
        $this->assertNull($arr['column']);
        $this->assertNull($arr['offset']);
    }

    public function testToStringIncludesSeverityAndRule(): void
    {
        $issue = new LintIssue(Severity::INFO, 'some-rule', 'hello', null);
        $str = (string) $issue;
        $this->assertStringContainsString('INFO', $str);
        $this->assertStringContainsString('some-rule', $str);
        $this->assertStringContainsString('hello', $str);
    }

    public function testSeverityIsAtLeast(): void
    {
        $this->assertTrue(Severity::ERROR->isAtLeast(Severity::WARNING));
        $this->assertTrue(Severity::ERROR->isAtLeast(Severity::INFO));
        $this->assertTrue(Severity::WARNING->isAtLeast(Severity::INFO));
        $this->assertFalse(Severity::INFO->isAtLeast(Severity::WARNING));
    }
}
