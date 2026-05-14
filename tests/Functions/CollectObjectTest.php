<?php

namespace Functions;

use FQL\Enum\Sort;
use FQL\Exception\InvalidArgumentException;
use FQL\Exception\SelectException;
use FQL\Functions\Aggregate\CollectObject as CollectObjectAggregate;
use FQL\Functions\FunctionRegistry;
use FQL\Query\Builder\CollectObject;
use FQL\Query\Provider as QueryProvider;
use FQL\Sql\Parser\ParseException;
use FQL\Stream\Csv;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage for the COLLECT_OBJECT aggregate — both via the FQL string
 * compiler and the fluent builder. Each test reuses a small in-memory CSV
 * fixture (`products`) covering two categories with mixed prices, so ORDER BY
 * direction and ROUND precision are observable.
 */
final class CollectObjectTest extends TestCase
{
    private string $csv = '';

    protected function setUp(): void
    {
        $this->csv = (string) tempnam(sys_get_temp_dir(), 'fql-co-') . '.csv';
        file_put_contents($this->csv, <<<DATA
productId,categoryId,productName,code,price
1,10,Apple,A1,1.235
2,10,Banana,B2,0.50
3,20,Carrot,C3,2.00
4,20,Dill,D4,1.99
5,10,Cherry,CH,2.50
DATA);
    }

    protected function tearDown(): void
    {
        if (is_file($this->csv)) {
            @unlink($this->csv);
        }
    }

    public function testRegisteredInFunctionRegistry(): void
    {
        $this->assertSame(CollectObjectAggregate::class, FunctionRegistry::getAggregate('COLLECT_OBJECT'));
        $this->assertTrue(FunctionRegistry::isAggregate('COLLECT_OBJECT'));
    }

    public function testFqlBasicGroupBy(): void
    {
        $sql = sprintf(
            "SELECT categoryId, COLLECT_OBJECT(productId AS id, productName AS name) AS products "
            . "FROM csv(%s).* GROUP BY categoryId",
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $this->assertCount(2, $rows);

        $byCategory = $this->indexByCategory($rows);
        $this->assertCount(3, $byCategory['10']['products']);
        $this->assertCount(2, $byCategory['20']['products']);
        // CSV reader hands values back as raw strings; we don't apply a scalar
        // wrapper here so the literal types pass through.
        $this->assertSame(['id' => '1', 'name' => 'Apple'], $byCategory['10']['products'][0]);
    }

    public function testFqlWithOrderByDesc(): void
    {
        $sql = sprintf(
            "SELECT categoryId, COLLECT_OBJECT(productName AS name, ROUND(price, 2) AS price ORDER BY price DESC) AS products "
            . "FROM csv(%s).* GROUP BY categoryId",
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);

        $prices10 = array_column($byCategory['10']['products'], 'price');
        $this->assertSame([2.5, 1.24, 0.5], $prices10);

        $prices20 = array_column($byCategory['20']['products'], 'price');
        $this->assertSame([2.0, 1.99], $prices20);
    }

    public function testFqlWithOrderByAsc(): void
    {
        $sql = sprintf(
            "SELECT categoryId, COLLECT_OBJECT(productName AS name, price ORDER BY price ASC) AS products "
            . "FROM csv(%s).* GROUP BY categoryId",
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);

        $names10 = array_column($byCategory['10']['products'], 'name');
        $this->assertSame(['Banana', 'Apple', 'Cherry'], $names10);
    }

    public function testFqlWithMultipleOrderByKeys(): void
    {
        $sql = sprintf(
            "SELECT categoryId, COLLECT_OBJECT(productName AS name, code AS code ORDER BY code ASC, name DESC) AS products "
            . "FROM csv(%s).* GROUP BY categoryId",
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);
        $codes10 = array_column($byCategory['10']['products'], 'code');
        $this->assertSame(['A1', 'B2', 'CH'], $codes10);
    }

    public function testFqlWithScalarFunctions(): void
    {
        $sql = sprintf(
            'SELECT categoryId, '
            . 'COLLECT_OBJECT('
            . 'productId AS id, '
            . 'CONCAT(productName, " (", code, ")") AS label, '
            . 'ROUND(price, 2) AS price'
            . ') AS products '
            . 'FROM csv(%s).* GROUP BY categoryId',
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);
        $first = $byCategory['10']['products'][0];
        $this->assertSame('Apple (A1)', $first['label']);
        $this->assertSame(1.24, $first['price']);
    }

    public function testFqlArithmeticInside(): void
    {
        $sql = sprintf(
            "SELECT categoryId, COLLECT_OBJECT(productId AS id, ROUND(price * 1.21, 2) AS priceWithVat) AS products "
            . "FROM csv(%s).* GROUP BY categoryId",
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);
        $apple = $this->findById($byCategory['10']['products'], 1);
        $this->assertSame(round(1.235 * 1.21, 2), $apple['priceWithVat']);
    }

    public function testFqlCombinedWithOtherAggregates(): void
    {
        $sql = sprintf(
            "SELECT categoryId, "
            . "COUNT(productId) AS cnt, "
            . "SUM(price) AS total, "
            . "GROUP_CONCAT(productName, \"|\") AS names, "
            . "COLLECT_OBJECT(productId AS id) AS items "
            . "FROM csv(%s).* GROUP BY categoryId",
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);
        $this->assertSame(3, $byCategory['10']['cnt']);
        $this->assertEqualsWithDelta(1.235 + 0.50 + 2.50, $byCategory['10']['total'], 1e-9);
        $this->assertSame('Apple|Banana|Cherry', $byCategory['10']['names']);
        $this->assertCount(3, $byCategory['10']['items']);
    }

    public function testFqlGroupOfOne(): void
    {
        $singleCsv = (string) tempnam(sys_get_temp_dir(), 'fql-co-1-') . '.csv';
        file_put_contents($singleCsv, "productId,categoryId,productName,price\n1,99,Only,5\n");
        try {
            $sql = sprintf(
                "SELECT categoryId, COLLECT_OBJECT(productName AS name) AS items FROM csv(%s).* GROUP BY categoryId",
                $singleCsv
            );
            $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
            $this->assertCount(1, $rows);
            $this->assertCount(1, $rows[0]['items']);
            $this->assertSame('Only', $rows[0]['items'][0]['name']);
        } finally {
            @unlink($singleCsv);
        }
    }

    public function testFluentParityWithFqlString(): void
    {
        $expectedSql = sprintf(
            "SELECT categoryId, COLLECT_OBJECT(productId AS id, productName AS name, ROUND(price, 2) AS price ORDER BY price DESC) AS products "
            . "FROM csv(%s).* GROUP BY categoryId",
            $this->csv
        );
        $expected = iterator_to_array(QueryProvider::fql($expectedSql)->execute()->fetchAll(), false);

        $fluent = Csv::openWithDelimiter($this->csv)->query()
            ->select('categoryId')
            ->collectObject(
                (new CollectObject())
                    ->select('productId')->as('id')
                    ->select('productName')->as('name')
                    ->select('ROUND(price, 2)')->as('price')
                    ->orderBy('price')->desc()
            )->as('products')
            ->groupBy('categoryId');
        $actual = iterator_to_array($fluent->execute()->fetchAll(), false);

        $this->assertEquals(
            $this->indexByCategory($expected),
            $this->indexByCategory($actual)
        );
    }

    public function testFluentScalarFunctionsViaSelectString(): void
    {
        $fluent = Csv::openWithDelimiter($this->csv)->query()
            ->select('categoryId')
            ->collectObject(
                (new CollectObject())
                    ->select('productId AS id')
                    ->select('CONCAT(productName, " (", code, ")") AS label')
                    ->select('ROUND(price, 2) AS price')
                    ->orderBy('price', Sort::ASC)
            )->as('products')
            ->groupBy('categoryId');
        $rows = iterator_to_array($fluent->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);

        $names10 = array_column($byCategory['10']['products'], 'label');
        $this->assertSame(['Banana (B2)', 'Apple (A1)', 'Cherry (CH)'], $names10);
    }

    public function testFluentUpperLowerCoalesceViaSelectString(): void
    {
        $fluent = Csv::openWithDelimiter($this->csv)->query()
            ->select('categoryId')
            ->collectObject(
                (new CollectObject())
                    ->select(
                        'UPPER(productName) AS upper',
                        'LOWER(productName) AS lower',
                        'COALESCE(code, "none") AS codeOrNone'
                    )
            )->as('products')
            ->groupBy('categoryId');
        $rows = iterator_to_array($fluent->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);
        $first10 = $byCategory['10']['products'][0];
        $this->assertSame('APPLE', $first10['upper']);
        $this->assertSame('apple', $first10['lower']);
        $this->assertSame('A1', $first10['codeOrNone']);
    }

    public function testInMemoryPathWithOuterOrderBy(): void
    {
        // Outer ORDER BY forces InMemory path; inner COLLECT_OBJECT ORDER BY still works.
        $sql = sprintf(
            "SELECT categoryId, COLLECT_OBJECT(productName AS name ORDER BY name ASC) AS products "
            . "FROM csv(%s).* GROUP BY categoryId ORDER BY categoryId DESC",
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $this->assertSame('20', (string) $rows[0]['categoryId']);
        $this->assertSame('10', (string) $rows[1]['categoryId']);
        $names10 = array_column($rows[1]['products'], 'name');
        $this->assertSame(['Apple', 'Banana', 'Cherry'], $names10);
    }

    public function testStreamPathWithoutOuterSort(): void
    {
        $sql = sprintf(
            "SELECT categoryId, COLLECT_OBJECT(productName AS name ORDER BY name DESC) AS products "
            . "FROM csv(%s).* GROUP BY categoryId",
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);
        $this->assertSame(['Cherry', 'Banana', 'Apple'], array_column($byCategory['10']['products'], 'name'));
    }

    public function testOrderByCanReferenceProjectedAliases(): void
    {
        // The inner SELECT inside COLLECT_OBJECT runs as a real Query over the
        // accumulated rows; ORDER BY therefore sees the projected aliases, not
        // just the raw source columns. This matches standard SQL semantics.
        $sql = sprintf(
            "SELECT categoryId, COLLECT_OBJECT(productName AS name, ROUND(price, 2) AS rounded ORDER BY rounded DESC) AS products "
            . "FROM csv(%s).* GROUP BY categoryId",
            $this->csv
        );
        $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
        $byCategory = $this->indexByCategory($rows);
        $this->assertSame([2.5, 1.24, 0.5], array_column($byCategory['10']['products'], 'rounded'));
    }

    public function testNullPropagatesIntoObject(): void
    {
        $csv = (string) tempnam(sys_get_temp_dir(), 'fql-co-n-') . '.csv';
        file_put_contents($csv, "productId,categoryId,productName,price\n1,1,Apple,\n2,1,Banana,3.5\n");
        try {
            $sql = sprintf(
                "SELECT categoryId, COLLECT_OBJECT(productName AS name, price AS price ORDER BY name ASC) AS products "
                . "FROM csv(%s).* GROUP BY categoryId",
                $csv
            );
            $rows = iterator_to_array(QueryProvider::fql($sql)->execute()->fetchAll(), false);
            // CSV empty cell → empty string after type matching; the entry survives.
            $this->assertCount(2, $rows[0]['products']);
            $this->assertSame('Apple', $rows[0]['products'][0]['name']);
        } finally {
            @unlink($csv);
        }
    }

    public function testAliasCollisionThrows(): void
    {
        $this->expectException(SelectException::class);
        $this->expectExceptionMessage('alias/key collision');
        Csv::openWithDelimiter($this->csv)->query()
            ->select('categoryId')
            ->collectObject(
                (new CollectObject())
                    ->select('productId AS x, productName AS x')
            )->as('products')
            ->groupBy('categoryId');
    }

    public function testDistinctRejectedInFqlString(): void
    {
        $this->expectException(ParseException::class);
        $sql = sprintf(
            "SELECT COLLECT_OBJECT(DISTINCT productName) FROM csv(%s).*",
            $this->csv
        );
        QueryProvider::fql($sql);
    }

    public function testNestedCollectObjectRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested COLLECT_OBJECT');
        Csv::openWithDelimiter($this->csv)->query()
            ->select('categoryId')
            ->collectObject(
                (new CollectObject())
                    ->select('COLLECT_OBJECT(productId) AS inner')
            )->as('products')
            ->groupBy('categoryId');
    }

    public function testEmptyCollectObjectRejectedByParser(): void
    {
        $this->expectException(ParseException::class);
        $sql = sprintf(
            "SELECT COLLECT_OBJECT() FROM csv(%s).*",
            $this->csv
        );
        QueryProvider::fql($sql);
    }

    public function testDescBeforeOrderByThrows(): void
    {
        // Inherited from the Sortable trait, which raises OrderByException
        // when ->desc()/->asc() is called before any ->orderBy().
        $this->expectException(\FQL\Exception\OrderByException::class);
        (new CollectObject())->desc();
    }

    public function testAsBeforeSelectThrows(): void
    {
        $this->expectException(\LogicException::class);
        (new CollectObject())->as('foo');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexByCategory(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['categoryId']] = $row;
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function findById(array $items, int $id): array
    {
        foreach ($items as $item) {
            if ((int) $item['id'] === $id) {
                return $item;
            }
        }
        $this->fail("Item with id=$id not found");
    }
}
