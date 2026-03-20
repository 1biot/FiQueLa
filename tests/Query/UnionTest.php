<?php

namespace Query;

use FQL\Enum\Operator;
use FQL\Exception\QueryLogicException;
use FQL\Sql\Sql;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class UnionTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');
        $this->json = Json::open($jsonFile);
    }

    public function testUnionRemovesDuplicates(): void
    {
        // Both queries select products with price >= 300, so results overlap
        $query1 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN_OR_EQUAL, 300);

        $query2 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN_OR_EQUAL, 300);

        $rows = iterator_to_array($query1->union($query2)->execute()->fetchAll());

        // Products 3, 4, 5 have price >= 300 — UNION deduplicates
        $this->assertCount(3, $rows);
        $this->assertSame([3, 4, 5], array_column($rows, 'id'));
    }

    public function testUnionAllKeepsDuplicates(): void
    {
        $query1 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN_OR_EQUAL, 300);

        $query2 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN_OR_EQUAL, 300);

        $rows = iterator_to_array($query1->unionAll($query2)->execute()->fetchAll());

        // 3 + 3 = 6 rows, duplicates kept
        $this->assertCount(6, $rows);
    }

    public function testUnionCombinesDifferentResultSets(): void
    {
        // Query1: cheap products (price <= 100)
        $query1 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('price', Operator::LESS_THAN_OR_EQUAL, 100);

        // Query2: expensive products (price >= 400)
        $query2 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('price', Operator::GREATER_THAN_OR_EQUAL, 400);

        $rows = iterator_to_array($query1->union($query2)->execute()->fetchAll());

        $ids = array_column($rows, 'id');
        sort($ids);
        // Product 1 (price=100), Product 4 (price=400), Product 5 (price=400)
        $this->assertSame([1, 4, 5], $ids);
    }

    public function testChainedUnions(): void
    {
        $query1 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('id', Operator::EQUAL, 1);

        $query2 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('id', Operator::EQUAL, 3);

        $query3 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products')
            ->where('id', Operator::EQUAL, 5);

        $rows = iterator_to_array(
            $query1->union($query2)->unionAll($query3)->execute()->fetchAll()
        );

        $this->assertCount(3, $rows);
        $this->assertSame([1, 3, 5], array_column($rows, 'id'));
    }

    public function testUnionColumnCountMismatchThrowsException(): void
    {
        $query1 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products');

        $query2 = $this->json->query()
            ->select('id')
            ->from('data.products');

        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('UNION query #1 has 1 columns, but main query has 2 columns');

        $query1->union($query2)->execute();
    }

    public function testUnionToString(): void
    {
        $query1 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products');

        $query2 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products');

        $sql = (string) $query1->union($query2);

        $this->assertStringContainsString('UNION', $sql);
    }

    public function testUnionAllToString(): void
    {
        $query1 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products');

        $query2 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products');

        $sql = (string) $query1->unionAll($query2);

        $this->assertStringContainsString('UNION ALL', $sql);
    }

    public function testUnionWithSelectAllSkipsColumnValidation(): void
    {
        $query1 = $this->json->query()
            ->from('data.products');

        $query2 = $this->json->query()
            ->select('id', 'name')
            ->from('data.products');

        // Should not throw — main query uses SELECT *
        $rows = iterator_to_array($query1->union($query2)->execute()->fetchAll());
        $this->assertNotEmpty($rows);
    }

    public function testFqlStringUnion(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');

        $sql = sprintf(
            'SELECT id, name FROM [json](%s).data.products WHERE price <= 100 UNION SELECT id, name FROM [json](%s).data.products WHERE price >= 400',
            $jsonFile,
            $jsonFile
        );

        $results = (new Sql($sql))->parse();
        $rows = iterator_to_array($results->fetchAll());

        $ids = array_column($rows, 'id');
        sort($ids);
        $this->assertSame([1, 4, 5], $ids);
    }

    public function testFqlStringUnionAll(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');

        $sql = sprintf(
            'SELECT id, name FROM [json](%s).data.products WHERE price >= 300 UNION ALL SELECT id, name FROM [json](%s).data.products WHERE price >= 300',
            $jsonFile,
            $jsonFile
        );

        $results = (new Sql($sql))->parse();
        $rows = iterator_to_array($results->fetchAll());

        // 3 + 3 = 6, duplicates kept
        $this->assertCount(6, $rows);
    }

    public function testFqlStringChainedUnion(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');

        $sql = sprintf(
            'SELECT id, name FROM [json](%s).data.products WHERE id = 1 UNION SELECT id, name FROM [json](%s).data.products WHERE id = 3 UNION ALL SELECT id, name FROM [json](%s).data.products WHERE id = 5',
            $jsonFile,
            $jsonFile,
            $jsonFile
        );

        $results = (new Sql($sql))->parse();
        $rows = iterator_to_array($results->fetchAll());

        $this->assertCount(3, $rows);
        $this->assertSame([1, 3, 5], array_column($rows, 'id'));
    }
}
