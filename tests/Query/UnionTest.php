<?php

namespace Query;

use FQL\Enum\Operator;
use FQL\Exception\QueryLogicException;
use FQL\Sql\Provider as SqlProvider;
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
            'SELECT id, name FROM json(%s).data.products WHERE price <= 100 UNION SELECT id, name FROM json(%s).data.products WHERE price >= 400',
            $jsonFile,
            $jsonFile
        );

        $results = SqlProvider::compile($sql)->toQuery()->execute();
        $rows = iterator_to_array($results->fetchAll());

        $ids = array_column($rows, 'id');
        sort($ids);
        $this->assertSame([1, 4, 5], $ids);
    }

    public function testFqlStringUnionAll(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');

        $sql = sprintf(
            'SELECT id, name FROM json(%s).data.products WHERE price >= 300 UNION ALL SELECT id, name FROM json(%s).data.products WHERE price >= 300',
            $jsonFile,
            $jsonFile
        );

        $results = SqlProvider::compile($sql)->toQuery()->execute();
        $rows = iterator_to_array($results->fetchAll());

        // 3 + 3 = 6, duplicates kept
        $this->assertCount(6, $rows);
    }

    public function testFqlStringChainedUnion(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');

        $sql = sprintf(
            'SELECT id, name FROM json(%s).data.products WHERE id = 1 UNION SELECT id, name FROM json(%s).data.products WHERE id = 3 UNION ALL SELECT id, name FROM json(%s).data.products WHERE id = 5',
            $jsonFile,
            $jsonFile,
            $jsonFile
        );

        $results = SqlProvider::compile($sql)->toQuery()->execute();
        $rows = iterator_to_array($results->fetchAll());

        $this->assertCount(3, $rows);
        $this->assertSame([1, 3, 5], array_column($rows, 'id'));
    }

    private const CATALOG = '{"data":[{"id":1,"price":300,"cat":"a"},{"id":2,"price":100,"cat":"b"},'
        . '{"id":3,"price":500,"cat":"a"},{"id":4,"price":200,"cat":"b"}]}';

    private function catalog(): \FQL\Interface\Stream
    {
        return Json::string(self::CATALOG);
    }

    private function catalogFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'fql-union-') . '.json';
        file_put_contents($file, self::CATALOG);
        return $file;
    }

    public function testInMemoryUnionAllWithOrderByKeepsRowsOfAllBranches(): void
    {
        $stream = $this->catalog();
        $query2 = $stream->query()->select('id', 'price')->from('data')->where('cat', Operator::EQUAL, 'b');
        $results = $stream->query()
            ->select('id', 'price')
            ->from('data')
            ->where('cat', Operator::EQUAL, 'a')
            ->orderBy('price')->desc()
            ->unionAll($query2)
            ->execute();

        $this->assertInstanceOf(\FQL\Results\InMemory::class, $results);
        $this->assertSame(4, $results->count());
        $this->assertSame(
            [[3, 500], [1, 300], [2, 100], [4, 200]],
            array_map(fn ($r) => [$r['id'], $r['price']], iterator_to_array($results->fetchAll()))
        );
    }

    public function testInMemoryUnionMatchesStreamResult(): void
    {
        $stream = $this->catalog();
        $build = fn () => $stream->query()
            ->select('id', 'price')
            ->from('data')
            ->where('cat', Operator::EQUAL, 'a')
            ->orderBy('price')->desc()
            ->union($stream->query()->select('id', 'price')->from('data')->where('cat', Operator::EQUAL, 'b'));

        $inMemory = iterator_to_array($build()->execute()->fetchAll());
        $streamed = iterator_to_array($build()->execute(\FQL\Results\Stream::class)->fetchAll());
        $this->assertCount(4, $inMemory);
        $this->assertSame($streamed, $inMemory);
    }

    public function testInMemoryJoinWithUnionKeepsRowsOfAllBranches(): void
    {
        $stream = $this->catalog();
        $results = $stream->query()
            ->select('id', 'price', 'c.cat')->as('joined')
            ->from('data')
            ->where('cat', Operator::EQUAL, 'a')
            ->leftJoin($stream->query()->select('id', 'cat')->from('data'), 'c')
            ->on('id', Operator::EQUAL, 'id')
            ->unionAll(
                $stream->query()->select('id', 'price', 'cat')->from('data')->where('cat', Operator::EQUAL, 'b')
            )
            ->execute();

        $this->assertInstanceOf(\FQL\Results\InMemory::class, $results);
        $rows = iterator_to_array($results->fetchAll());
        $this->assertSame([1, 3, 2, 4], array_column($rows, 'id'));
    }

    public function testFqlOrderByAndLimitAfterUnionBindToLastBranch(): void
    {
        $file = $this->catalogFile();
        $sql = 'SELECT id, price FROM json(' . $file . ').data WHERE cat = "a"'
            . ' UNION ALL SELECT id, price FROM json(' . $file . ').data WHERE cat = "b" ORDER BY price DESC LIMIT 1';
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        // ORDER BY / LIMIT belong to the last branch only: branch "a" stays as read,
        // branch "b" is sorted and cut to one row.
        $this->assertSame([[1, 300], [3, 500], [4, 200]], array_map(fn ($r) => [$r['id'], $r['price']], $rows));
        unlink($file);
    }

    public function testFqlWithClauseSortsAndLimitsWholeUnion(): void
    {
        $file = $this->catalogFile();
        $sql = 'WITH u AS (SELECT id, price FROM json(' . $file . ').data WHERE cat = "a"'
            . ' UNION ALL SELECT id, price FROM json(' . $file . ').data WHERE cat = "b")'
            . ' SELECT id, price FROM u ORDER BY price DESC LIMIT 3';
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertSame([[3, 500], [1, 300], [4, 200]], array_map(fn ($r) => [$r['id'], $r['price']], $rows));
        unlink($file);
    }
}
