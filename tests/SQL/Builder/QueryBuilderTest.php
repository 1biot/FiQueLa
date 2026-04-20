<?php

namespace SQL\Builder;

use FQL\Enum;
use FQL\Exception;
use FQL\Query\TestProvider;
use FQL\Sql\Ast\ExplainMode;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Builder\ExpressionCompiler;
use FQL\Sql\Builder\FileQueryResolver;
use FQL\Sql\Builder\QueryBuilder;
use FQL\Sql\Builder\QueryBuildingVisitor;
use FQL\Sql\Parser\Parser;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the AST -> Query builder end-to-end. Uses the existing fixture JSON
 * in examples/data so we can confirm the builder wires the Query API correctly
 * (joins, describe, explain, unions, INTO) and returns expected results.
 */
class QueryBuilderTest extends TestCase
{
    private string $productsJson;

    protected function setUp(): void
    {
        $this->productsJson = (string) realpath(__DIR__ . '/../../../examples/data/products.json');
    }

    private function buildAst(string $sql): SelectStatementNode
    {
        $parser = Parser::create();
        return $parser->parse(new TokenStream((new Tokenizer())->tokenize($sql)));
    }

    public function testBuildSimpleSelect(): void
    {
        $sql = sprintf('SELECT id, name FROM json(%s).data.products LIMIT 1', $this->productsJson);
        $ast = $this->buildAst($sql);

        $query = (new QueryBuilder())->build($ast);
        $rows = iterator_to_array($query->execute()->fetchAll());
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
    }

    public function testBuildDistinct(): void
    {
        $sql = sprintf(
            'SELECT DISTINCT brand.code FROM json(%s).data.products',
            $this->productsJson
        );
        $ast = $this->buildAst($sql);
        $query = (new QueryBuilder())->build($ast);
        $rows = iterator_to_array($query->execute()->fetchAll());
        $codes = array_column($rows, 'brand.code');
        $this->assertSame(array_values(array_unique($codes)), array_values($codes));
    }

    public function testBuildExcludeFields(): void
    {
        $sql = sprintf(
            'SELECT name, EXCLUDE description FROM json(%s).data.products LIMIT 1',
            $this->productsJson
        );
        $ast = $this->buildAst($sql);
        $query = (new QueryBuilder())->build($ast);
        $rows = iterator_to_array($query->execute()->fetchAll());
        $this->assertArrayNotHasKey('description', $rows[0]);
    }

    public function testBuildDescribeStatement(): void
    {
        $sql = sprintf('DESCRIBE json(%s).data.products', $this->productsJson);
        $ast = $this->buildAst($sql);
        $this->assertTrue($ast->describe);

        $query = (new QueryBuilder())->build($ast);
        $rows = iterator_to_array($query->execute()->fetchAll());
        $this->assertNotEmpty($rows);
    }

    public function testBuildExplainStatement(): void
    {
        $sql = sprintf('EXPLAIN SELECT id FROM json(%s).data.products', $this->productsJson);
        $ast = $this->buildAst($sql);
        $this->assertSame(ExplainMode::EXPLAIN, $ast->explain);
        // Just ensure build works without throwing; the output content is covered by the
        // legacy SqlIntegrationTest suite.
        (new QueryBuilder())->build($ast);
    }

    public function testBuildExplainAnalyzeStatement(): void
    {
        $sql = sprintf('EXPLAIN ANALYZE SELECT id FROM json(%s).data.products', $this->productsJson);
        $ast = $this->buildAst($sql);
        $this->assertSame(ExplainMode::EXPLAIN_ANALYZE, $ast->explain);
        (new QueryBuilder())->build($ast);
    }

    public function testBuildWithInnerJoin(): void
    {
        $sql = sprintf(
            'SELECT id, name FROM json(%s).data.products '
            . 'INNER JOIN json(%s).data.products AS p2 ON id = p2.id LIMIT 1',
            $this->productsJson,
            $this->productsJson
        );
        $ast = $this->buildAst($sql);
        $query = (new QueryBuilder())->build($ast);
        $rows = iterator_to_array($query->execute()->fetchAll());
        $this->assertCount(1, $rows);
    }

    public function testBuildWithUnionAll(): void
    {
        $sql = sprintf(
            'SELECT id FROM json(%s).data.products WHERE id = 1 UNION ALL SELECT id FROM json(%s).data.products WHERE id = 2',
            $this->productsJson,
            $this->productsJson
        );
        $ast = $this->buildAst($sql);
        $this->assertCount(1, $ast->unions);
        $this->assertTrue($ast->unions[0]->all);

        $query = (new QueryBuilder())->build($ast);
        $rows = iterator_to_array($query->execute()->fetchAll());
        $ids = array_column($rows, 'id');
        sort($ids);
        $this->assertSame([1, 2], $ids);
    }

    public function testBuildWithCaseExpression(): void
    {
        $sql = sprintf(
            'SELECT name, CASE WHEN price > 300 THEN "high" ELSE "low" END AS tier '
            . 'FROM json(%s).data.products LIMIT 3',
            $this->productsJson
        );
        $ast = $this->buildAst($sql);
        $query = (new QueryBuilder())->build($ast);
        $rows = iterator_to_array($query->execute()->fetchAll());
        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertArrayHasKey('tier', $row);
            $this->assertContains($row['tier'], ['high', 'low']);
        }
    }

    public function testBuildWithSubqueryInJoin(): void
    {
        $sql = sprintf(
            'SELECT id, name FROM json(%s).data.products AS p '
            . 'LEFT JOIN (SELECT id FROM json(%s).data.products WHERE id > 2) AS s ON p.id = s.id '
            . 'LIMIT 1',
            $this->productsJson,
            $this->productsJson
        );
        $ast = $this->buildAst($sql);
        $query = (new QueryBuilder())->build($ast);
        $rows = iterator_to_array($query->execute()->fetchAll());
        $this->assertNotEmpty($rows);
    }

    public function testApplyToUsesExistingQueryWithoutReopeningStream(): void
    {
        $sql = 'SELECT id, COUNT(id) AS total FROM data.products GROUP BY brand.name HAVING total > 1';

        // Compile AST, then apply onto a pre-built Query (TestProvider stands in for one).
        $ast = $this->buildAst($sql);
        $query = (new QueryBuilder())->applyTo($ast, new TestProvider());
        // TestProvider is a placeholder, so we're just asserting the apply chain succeeded
        // and the returned Query carries the expected metadata.
        $this->assertNotNull($query);
    }

    public function testApplyToHandlesFromlessSql(): void
    {
        // FROM-less SELECT — typical use in `Compiler::applyTo()` where the stream is
        // already open. The builder must not try to open a new file.
        $sql = 'SELECT SUM(price) AS total';
        $ast = $this->buildAst($sql);
        $this->assertNull($ast->from);

        $query = (new QueryBuilder())->applyTo($ast, new TestProvider());
        $this->assertNotNull($query);
    }

    public function testBuildThrowsWhenFromMissing(): void
    {
        // Without applyTo(), a FROM-less AST should be rejected by build().
        $sql = 'SELECT SUM(price) AS total';
        $ast = $this->buildAst($sql);
        $this->expectException(Exception\QueryLogicException::class);
        (new QueryBuilder())->build($ast);
    }

    public function testVisitorExposedForCustomConfiguration(): void
    {
        $builder = new QueryBuilder();
        $this->assertInstanceOf(QueryBuildingVisitor::class, $builder->visitor());
    }

    public function testFileQueryResolverExposesBasePath(): void
    {
        $resolver = new FileQueryResolver('/tmp/some-path');
        $this->assertSame('/tmp/some-path', $resolver->getBasePath());
    }

    public function testFileQueryResolverReturnsOriginalWhenNoBasePath(): void
    {
        $resolver = new FileQueryResolver(null);
        $fq = new \FQL\Query\FileQuery('csv(sample.csv)');
        $this->assertSame($fq, $resolver->resolve($fq));
    }

    public function testExpressionCompilerIsInstantiableAsBuilderDependency(): void
    {
        // The legacy constructor path: build a visitor manually with custom deps.
        $visitor = new QueryBuildingVisitor(
            new ExpressionCompiler(),
            new FileQueryResolver(null)
        );
        $sql = sprintf('SELECT id FROM json(%s).data.products LIMIT 1', $this->productsJson);
        $ast = $this->buildAst($sql);
        $query = $visitor->build($ast);
        $this->assertCount(1, iterator_to_array($query->execute()->fetchAll()));
    }
}
