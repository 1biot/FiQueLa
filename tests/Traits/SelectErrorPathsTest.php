<?php

namespace Traits;

use FQL\Enum\Type;
use FQL\Exception\AliasException;
use FQL\Exception\CaseException;
use FQL\Exception\QueryLogicException;
use FQL\Exception\SelectException;
use FQL\Query\TestProvider;
use FQL\Sql\Ast\Expression\CastExpressionNode;
use PHPUnit\Framework\TestCase;

/**
 * Covers the error / rare branches of `Traits\Select` — aliasing edge cases,
 * blocked state, CAST node wiring, CASE builder guards.
 */
class SelectErrorPathsTest extends TestCase
{
    public function testSelectBlockedInDescribeMode(): void
    {
        $q = new TestProvider();
        $q->blockSelect();
        $this->expectException(QueryLogicException::class);
        $q->select('name');
    }

    public function testExcludeBlockedInDescribeMode(): void
    {
        $q = new TestProvider();
        $q->blockSelect();
        $this->expectException(QueryLogicException::class);
        $q->exclude('secret');
    }

    public function testDistinctBlockedInDescribeMode(): void
    {
        $q = new TestProvider();
        $q->blockSelect();
        $this->expectException(QueryLogicException::class);
        $q->distinct();
    }

    public function testAsEmptyAliasThrows(): void
    {
        $q = new TestProvider();
        $q->select('name');
        $this->expectException(AliasException::class);
        $q->as('');
    }

    public function testAsWithoutSelectedFieldThrows(): void
    {
        $q = new TestProvider();
        $this->expectException(AliasException::class);
        $q->as('x');
    }

    public function testAsOnAlreadyAliasedFieldThrows(): void
    {
        $q = new TestProvider();
        $q->select('name')->as('alias1');
        $this->expectException(AliasException::class);
        $q->as('alias2');
    }

    public function testCastFluentHelperStoresCastExpressionNode(): void
    {
        $q = new TestProvider();
        $q->cast('age', Type::INTEGER);
        $fields = $q->getSelectedFields();
        $this->assertCount(1, $fields);
        $entry = reset($fields);
        $this->assertInstanceOf(CastExpressionNode::class, $entry['expression']);
    }

    public function testWhenCaseWithoutPriorCaseThrows(): void
    {
        $q = new TestProvider();
        $this->expectException(CaseException::class);
        $q->whenCase('x > 1', '"big"');
    }

    public function testElseCaseWithoutPriorCaseThrows(): void
    {
        $q = new TestProvider();
        $this->expectException(CaseException::class);
        $q->elseCase('"default"');
    }

    public function testElseCaseWithoutWhenThrows(): void
    {
        $q = new TestProvider();
        $q->case();
        $this->expectException(CaseException::class);
        $q->elseCase('"default"');
    }

    public function testElseCaseCalledTwiceThrows(): void
    {
        $q = new TestProvider();
        $q->case()->whenCase('x > 1', '"big"')->elseCase('"first"');
        $this->expectException(CaseException::class);
        $q->elseCase('"second"');
    }

    public function testEndCaseWithoutCaseThrows(): void
    {
        $q = new TestProvider();
        $this->expectException(CaseException::class);
        $q->endCase();
    }

    public function testDuplicateAliasThrows(): void
    {
        $q = new TestProvider();
        $q->select('name')->as('x');
        // Trying to alias another field to "x" should collide.
        $this->expectException(AliasException::class);
        $q->select('other')->as('x');
    }

    public function testExcludeStoresFields(): void
    {
        $q = new TestProvider();
        $q->exclude('a', 'b');
        $this->assertSame(['a', 'b'], $q->getExcludedFields());
    }

    public function testSelectDuplicateFieldThrows(): void
    {
        $q = new TestProvider();
        $this->expectException(SelectException::class);
        $q->select('name')->select('name');
    }
}
