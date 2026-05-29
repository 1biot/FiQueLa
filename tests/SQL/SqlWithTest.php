<?php

namespace SQL;

use FQL\Sql\Ast\Expression\CteReferenceNode;
use FQL\Sql\Ast\Node\CommonTableExpressionNode;
use FQL\Sql\Builder\CteReferenceCounter;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Provider as SqlProvider;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the WITH clause: parser, builder, error paths and execution
 * semantics. Tests rely on the same JSON/XML fixtures as the rest of the SQL
 * test suite to keep behaviour reproducible against real streams.
 */
class SqlWithTest extends TestCase
{
    private string $usersJson;
    private string $ordersXml;

    protected function setUp(): void
    {
        $this->usersJson = realpath(__DIR__ . '/../../examples/data/users.json');
        $this->ordersXml = realpath(__DIR__ . '/../../examples/data/orders.xml');
    }

    public function testParserBuildsCommonTableExpressionNode(): void
    {
        $sql = sprintf(
            'WITH active AS (SELECT id, name FROM json(%s).data.users WHERE id > 0) SELECT name FROM active',
            $this->usersJson
        );
        $ast = SqlProvider::compile($sql)->toAst();

        $this->assertCount(1, $ast->commonTables);
        $cte = $ast->commonTables[0];
        $this->assertInstanceOf(CommonTableExpressionNode::class, $cte);
        $this->assertSame('active', $cte->name);

        $this->assertNotNull($ast->from);
        $this->assertInstanceOf(CteReferenceNode::class, $ast->from->source);
        $this->assertSame('active', $ast->from->source->name);
    }

    public function testSingleCteFromExecutes(): void
    {
        $sql = sprintf(
            'WITH active AS (SELECT id, name FROM json(%s).data.users WHERE id > 0) '
            . 'SELECT name FROM active LIMIT 2',
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('name', $rows[0]);
    }

    public function testMultipleCteForwardChain(): void
    {
        // CTE `b` references CTE `a` — forward-chaining must be visible at parse and build time.
        $sql = sprintf(
            'WITH a AS (SELECT id, name FROM json(%s).data.users WHERE id > 0), '
            . '     b AS (SELECT name FROM a) '
            . 'SELECT name FROM b LIMIT 2',
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('name', $rows[0]);
    }

    public function testCteInJoinSource(): void
    {
        $sql = sprintf(
            'WITH adults AS (SELECT id, name FROM json(%s).data.users WHERE id > 0) '
            . 'SELECT a.name FROM adults AS a '
            . 'INNER JOIN json(%s).data.users AS u ON id = u.id LIMIT 2',
            $this->usersJson,
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('a.name', $rows[0]);
    }

    public function testCteReferencedTwiceMaterialisesOnceAndSharesRows(): void
    {
        // Self-JOIN over a CTE — the source is small enough that both reference
        // points see exactly the rows the CTE selected (id < 3 → ids 1 and 2).
        $sql = sprintf(
            'WITH small AS (SELECT id, name FROM json(%s).data.users WHERE id < 3) '
            . 'SELECT s1.name FROM small AS s1 INNER JOIN small AS s2 ON id = s2.id',
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertCount(2, $rows);
        $this->assertSame(['John Doe', 'John Doe 2'], array_column($rows, 's1.name'));
    }

    public function testCteInUnionRightHandSideBranch(): void
    {
        $sql = sprintf(
            'WITH only_first AS (SELECT id, name FROM json(%s).data.users WHERE id = 1) '
            . 'SELECT name FROM only_first '
            . 'UNION SELECT name FROM json(%s).data.users WHERE id = 2',
            $this->usersJson,
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertCount(2, $rows);
        $this->assertSame(['John Doe', 'John Doe 2'], array_column($rows, 'name'));
    }

    public function testExplainWorksWithWith(): void
    {
        $sql = sprintf(
            'EXPLAIN WITH active AS (SELECT id, name FROM json(%s).data.users WHERE id > 0) '
            . 'SELECT name FROM active',
            $this->usersJson
        );
        // EXPLAIN must round-trip through the builder without throwing.
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('phase', $rows[0]);
    }

    public function testExplainAnalyzeWorksWithWith(): void
    {
        $sql = sprintf(
            'EXPLAIN ANALYZE WITH active AS (SELECT id, name FROM json(%s).data.users WHERE id > 0) '
            . 'SELECT name FROM active',
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertNotEmpty($rows);
    }

    public function testUnknownCteNameSurfacesAsParseError(): void
    {
        $sql = sprintf(
            'WITH active AS (SELECT id FROM json(%s).data.users) SELECT * FROM unknown_cte',
            $this->usersJson
        );
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('unknown CTE "unknown_cte"');
        SqlProvider::compile($sql)->toQuery();
    }

    public function testDuplicateCteNameSurfacesAsParseError(): void
    {
        $sql = sprintf(
            'WITH a AS (SELECT id FROM json(%s).data.users), '
            . 'a AS (SELECT id FROM json(%s).data.users) SELECT * FROM a',
            $this->usersJson,
            $this->usersJson
        );
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('duplicate CTE name "a"');
        SqlProvider::compile($sql)->toAst();
    }

    public function testRecursiveCteIsExplicitlyRejected(): void
    {
        $sql = sprintf(
            'WITH RECURSIVE r AS (SELECT id FROM json(%s).data.users) SELECT * FROM r',
            $this->usersJson
        );
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('RECURSIVE CTEs are not supported');
        SqlProvider::compile($sql)->toAst();
    }

    public function testReferenceCounterTalliesPerCte(): void
    {
        // Counter is the single source of truth for the materialise-vs-inline
        // decision — pin its behaviour down explicitly.
        $sql = sprintf(
            'WITH a AS (SELECT id FROM json(%s).data.users), '
            . '     b AS (SELECT id FROM json(%s).data.users), '
            . '     c AS (SELECT id FROM json(%s).data.users) '
            . 'SELECT * FROM a INNER JOIN b AS b1 ON id = b1.id '
            . 'INNER JOIN b AS b2 ON id = b2.id',
            $this->usersJson,
            $this->usersJson,
            $this->usersJson
        );
        $ast = SqlProvider::compile($sql)->toAst();
        $counts = (new CteReferenceCounter())->count($ast);

        $this->assertSame(1, $counts['a'] ?? 0, 'a referenced once (FROM)');
        $this->assertSame(2, $counts['b'] ?? 0, 'b referenced twice (two JOINs)');
        $this->assertArrayNotHasKey('c', $counts, 'c is declared but unused');
    }

    public function testFormatterRoundTripsWithClause(): void
    {
        $sql = sprintf(
            'WITH active AS (SELECT id, name FROM json(%s).data.users WHERE id > 0) '
            . 'SELECT name FROM active',
            $this->usersJson
        );
        $formatted = SqlProvider::format($sql);

        $this->assertStringContainsString('WITH active AS (', $formatted);
        $this->assertStringContainsString('FROM active', $formatted);

        // Round-trip stability: re-parse and re-format yields identical output.
        $this->assertSame($formatted, SqlProvider::format($formatted));
    }

    public function testCteBodyAcceptsOrderByBeforeClosingParen(): void
    {
        // Regression for the bug surfaced by users hitting `ORDER BY ... )` —
        // ClauseBoundary::isControlKeyword() previously didn't list PAREN_CLOSE
        // as a clause terminator, so ORDER BY inside a CTE body failed with
        // "expected comma or end of clause".
        $sql = sprintf(
            'WITH top AS (SELECT id, name FROM json(%s).data.users WHERE id > 0 ORDER BY id DESC) '
            . 'SELECT name FROM top LIMIT 2',
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertCount(2, $rows);
        $this->assertSame(['John Doe 4', 'John Doe 3'], array_column($rows, 'name'));
    }

    public function testCteBodyAcceptsGroupByBeforeClosingParen(): void
    {
        $sql = sprintf(
            'WITH grouped AS (SELECT id FROM json(%s).data.users GROUP BY id) '
            . 'SELECT * FROM grouped LIMIT 2',
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
    }

    public function testCteBodyAcceptsGroupByThenOrderBy(): void
    {
        // Exact shape of the user's repro: SELECT ... GROUP BY ... ORDER BY ... DESC
        // immediately before the closing CTE paren.
        $sql = sprintf(
            'WITH top AS (SELECT id, name FROM json(%s).data.users GROUP BY id ORDER BY id DESC) '
            . 'SELECT * FROM top LIMIT 3',
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertCount(3, $rows);
        $ids = array_column($rows, 'id');
        $this->assertSame($ids, array_values(array_reverse(array_unique(array_reverse(array_column($rows, 'id'))))));
        // Sanity: DESC order means the first id is greater than the last.
        $this->assertGreaterThan(end($ids), $ids[0]);
    }

    public function testRuntimeQueryToStringIncludesWithClause(): void
    {
        // After build, the outer Query reads from the materialised CTE stream
        // (label = "active"). __toString must still surface the WITH clause and
        // a bare-identifier FROM so the dump reflects the original FQL shape
        // instead of the post-materialisation `FROM results(memory)` view.
        $sql = sprintf(
            'WITH active AS (SELECT id, name FROM json(%s).data.users WHERE id > 0) '
            . 'SELECT name FROM active',
            $this->usersJson
        );
        $rendered = (string) SqlProvider::compile($sql)->toQuery();

        $this->assertStringContainsString('WITH active AS (', $rendered);
        $this->assertStringContainsString('FROM active', $rendered);
        $this->assertStringNotContainsString('results(memory)', $rendered);
    }
}
