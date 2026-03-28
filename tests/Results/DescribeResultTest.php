<?php

namespace FQL\Tests\Results;

use FQL\Exception\QueryLogicException;
use FQL\Query\Provider;
use FQL\Results\DescribeResult;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class DescribeResultTest extends TestCase
{
    private Json $products;
    private string $productsFile;

    protected function setUp(): void
    {
        $productsFile = realpath(__DIR__ . '/../../examples/data/products.json');
        $this->assertNotFalse($productsFile);
        $this->productsFile = $productsFile;
        $this->products = Json::open($productsFile);
    }

    public function testDescribeReturnsDescribeResult(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        $this->assertInstanceOf(DescribeResult::class, $result);
    }

    public function testDescribeReturnsColumnsForJson(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        $columns = iterator_to_array($result->fetchAll());
        $columnNames = array_column($columns, 'column');

        // Products have: id, name, description, price, brand.code, brand.name, categories
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('description', $columnNames);
        $this->assertContains('price', $columnNames);
        $this->assertContains('brand.code', $columnNames);
        $this->assertContains('brand.name', $columnNames);
        $this->assertContains('categories', $columnNames);
    }

    public function testDescribeSourceRowCount(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        /** @var DescribeResult $result */
        // Must consume iterator before checking sourceRowCount
        iterator_to_array($result->fetchAll());

        $this->assertSame(5, $result->getSourceRowCount());
    }

    public function testDescribeDetectsIntegerType(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        $columns = iterator_to_array($result->fetchAll());
        $idColumn = $this->findColumn($columns, 'id');

        $this->assertNotNull($idColumn);
        $this->assertSame('int', $idColumn['dominant']);
        $this->assertSame(5, $idColumn['totalRows']);
        $this->assertTrue($idColumn['isUnique']);
    }

    public function testDescribeDetectsStringType(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        $columns = iterator_to_array($result->fetchAll());
        $nameColumn = $this->findColumn($columns, 'name');

        $this->assertNotNull($nameColumn);
        $this->assertSame('string', $nameColumn['dominant']);
        $this->assertTrue($nameColumn['isUnique']);
    }

    public function testDescribeDetectsNullableColumn(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        $columns = iterator_to_array($result->fetchAll());
        $descColumn = $this->findColumn($columns, 'description');

        $this->assertNotNull($descColumn);
        // description has 3 strings + 2 nulls
        $this->assertArrayHasKey('string', $descColumn['types']);
        $this->assertArrayHasKey('null', $descColumn['types']);
        $this->assertLessThan(1.0, $descColumn['completeness']);
    }

    public function testDescribeDetectsConstantColumn(): void
    {
        // Products 4 and 5 have same brand.code = "BRAND-B" and price = 400
        // brand.code has 4 unique values across 5 rows — not constant
        // Let's check price: 100, 200, 300, 400, 400 — 4 unique, not constant
        // brand.name has: Brand A, Brand C, Brand D, Brand B, Brand B — 4 unique
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        $columns = iterator_to_array($result->fetchAll());
        $priceColumn = $this->findColumn($columns, 'price');

        $this->assertNotNull($priceColumn);
        $this->assertFalse($priceColumn['constant']);
    }

    public function testDescribeFlattensNestedObjects(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        $columns = iterator_to_array($result->fetchAll());
        $columnNames = array_column($columns, 'column');

        // brand is an associative array, should be flattened
        $this->assertContains('brand.code', $columnNames);
        $this->assertContains('brand.name', $columnNames);
        // categories is an indexed array, should NOT be flattened
        $this->assertContains('categories', $columnNames);
        $this->assertNotContains('brand', $columnNames);
    }

    public function testDescribeConfidence(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        $columns = iterator_to_array($result->fetchAll());
        $idColumn = $this->findColumn($columns, 'id');

        $this->assertNotNull($idColumn);
        // All 5 values are int, confidence = 1.0
        $this->assertSame(1.0, $idColumn['confidence']);
    }

    public function testDescribeViaFql(): void
    {
        $result = Provider::fql(
            sprintf('DESCRIBE json(%s).data.products', $this->productsFile)
        )->execute();

        $this->assertInstanceOf(DescribeResult::class, $result);

        $columns = iterator_to_array($result->fetchAll());
        $columnNames = array_column($columns, 'column');

        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('price', $columnNames);
    }

    public function testDescribeToString(): void
    {
        $query = $this->products->query()
            ->from('data.products')
            ->describe();

        $sql = (string) $query;
        $this->assertStringStartsWith('DESCRIBE ', $sql);
        $this->assertStringContainsString('data.products', $sql);
    }

    public function testDescribeCannotCombineWithSelect(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('DESCRIBE cannot be combined with SELECT');

        $this->products->query()
            ->select('id')
            ->describe();
    }

    public function testDescribeCannotCombineWithWhere(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('DESCRIBE cannot be combined with WHERE');

        $this->products->query()
            ->from('data.products')
            ->where('id', \FQL\Enum\Operator::EQUAL, 1)
            ->describe();
    }

    public function testDescribeCannotCombineWithGroupBy(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('DESCRIBE cannot be combined with GROUP BY');

        $this->products->query()
            ->from('data.products')
            ->groupBy('name')
            ->describe();
    }

    public function testDescribeCannotCombineWithOrderBy(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('DESCRIBE cannot be combined with ORDER BY');

        $this->products->query()
            ->from('data.products')
            ->orderBy('name')
            ->describe();
    }

    public function testDescribeCannotCombineWithLimit(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('DESCRIBE cannot be combined with LIMIT');

        $this->products->query()
            ->from('data.products')
            ->limit(10)
            ->describe();
    }

    public function testDescribeCannotCombineWithJoin(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('DESCRIBE cannot be combined with JOIN');

        $this->products->query()
            ->from('data.products')
            ->leftJoin(Provider::fromFileQuery(sprintf('json(%s).data.products', $this->productsFile)), 'p')
            ->on('id', \FQL\Enum\Operator::EQUAL, 'id')
            ->describe();
    }

    public function testDescribeCannotCombineWithUnion(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('DESCRIBE cannot be combined with UNION');

        $other = $this->products->query()
            ->from('data.products');

        $this->products->query()
            ->from('data.products')
            ->union($other)
            ->describe();
    }

    public function testDescribeCannotCombineWithExplain(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('DESCRIBE cannot be combined with EXPLAIN');

        $this->products->query()
            ->from('data.products')
            ->explain()
            ->describe();
    }

    public function testDescribeBlocksSubsequentSelect(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('SELECT is not allowed in DESCRIBE mode');

        $this->products->query()
            ->from('data.products')
            ->describe()
            ->select('id');
    }

    public function testDescribeBlocksSubsequentWhere(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('WHERE/HAVING is not allowed in DESCRIBE mode');

        $this->products->query()
            ->from('data.products')
            ->describe()
            ->where('id', \FQL\Enum\Operator::EQUAL, 1);
    }

    public function testDescribeBlocksSubsequentGroupBy(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('GROUP BY is not allowed in DESCRIBE mode');

        $this->products->query()
            ->from('data.products')
            ->describe()
            ->groupBy('name');
    }

    public function testDescribeBlocksSubsequentOrderBy(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('ORDER BY is not allowed in DESCRIBE mode');

        $this->products->query()
            ->from('data.products')
            ->describe()
            ->orderBy('name');
    }

    public function testDescribeBlocksSubsequentLimit(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('LIMIT is not allowed in DESCRIBE mode');

        $this->products->query()
            ->from('data.products')
            ->describe()
            ->limit(10);
    }

    public function testDescribeBlocksSubsequentJoin(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('JOIN is not allowed in DESCRIBE mode');

        $this->products->query()
            ->from('data.products')
            ->describe()
            ->leftJoin(Provider::fromFileQuery(sprintf('json(%s).data.products', $this->productsFile)), 'p');
    }

    public function testDescribeBlocksSubsequentUnion(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('UNION is not allowed in DESCRIBE mode');

        $other = $this->products->query()
            ->from('data.products');

        $this->products->query()
            ->from('data.products')
            ->describe()
            ->union($other);
    }

    public function testDescribeBlocksSubsequentExplain(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('EXPLAIN is not allowed in DESCRIBE mode');

        $this->products->query()
            ->from('data.products')
            ->describe()
            ->explain();
    }

    public function testDescribeBlocksSubsequentExplainAnalyze(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('EXPLAIN is not allowed in DESCRIBE mode');

        $this->products->query()
            ->from('data.products')
            ->describe()
            ->explainAnalyze();
    }

    public function testDescribeCountReturnsNumberOfColumns(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        // At least id, name, description, price, brand.code, brand.name, categories = 7
        $this->assertGreaterThanOrEqual(7, $result->count());
    }

    public function testDescribeSuspiciousColumn(): void
    {
        $result = $this->products->query()
            ->from('data.products')
            ->describe()
            ->execute();

        $columns = iterator_to_array($result->fetchAll());
        $descColumn = $this->findColumn($columns, 'description');

        $this->assertNotNull($descColumn);
        // description: 3 strings + 2 nulls — null is empty type, 1 non-empty type => not suspicious
        $this->assertFalse($descColumn['suspicious']);
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array<string, mixed>|null
     */
    private function findColumn(array $columns, string $name): ?array
    {
        foreach ($columns as $col) {
            if ($col['column'] === $name) {
                return $col;
            }
        }
        return null;
    }
}
