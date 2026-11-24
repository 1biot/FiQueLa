<?php

namespace Results;

use FQL\Enum\Operator;
use FQL\Exception\QueryLogicException;
use FQL\Interface\Query;
use FQL\Sql\Provider as SqlProvider;
use FQL\Stream\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ORDER BY may name columns that are not selected or are removed by EXCLUDE:
 * the sort keys are computed in the projection (output row first, then the
 * source row) and travel with the row to the sort, which only compares them.
 */
class OrderByProjectionTest extends TestCase
{
    private const DATA = '{"data":['
        . '{"id":1,"price":300,"cat":"a","name":"Alpha"},'
        . '{"id":2,"price":100,"cat":"b","name":"Beta"},'
        . '{"id":3,"price":500,"cat":"a","name":"Gamma"},'
        . '{"id":4,"price":200,"cat":"b","name":"Delta"}'
        . '],"stock":[{"pid":1,"qty":5},{"pid":2,"qty":9},{"pid":3,"qty":1},{"pid":4,"qty":7}]}';

    private static string $file;

    public static function setUpBeforeClass(): void
    {
        self::$file = tempnam(sys_get_temp_dir(), 'fql-order-') . '.json';
        file_put_contents(self::$file, self::DATA);
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$file);
    }

    /**
     * @return array<int, array<int|string, mixed>>
     */
    private function fql(string $sql): array
    {
        $sql = str_replace('{f}', self::$file, $sql);
        return iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());
    }

    private function query(): Query
    {
        return Json::string(self::DATA)->query()->from('data');
    }

    /**
     * @return array<int, array<int|string, mixed>>
     */
    private function rows(Query $query): array
    {
        return iterator_to_array($query->execute()->fetchAll());
    }

    /**
     * @return array<string, array{string, array<int, array<string, mixed>>}>
     */
    public static function fqlCases(): array
    {
        $byPriceDesc = [['id' => 3], ['id' => 1], ['id' => 4], ['id' => 2]];
        return [
            'column not selected' => ['SELECT id FROM json({f}).data ORDER BY price DESC', $byPriceDesc],
            'excluded column' => ['SELECT id, price EXCLUDE price FROM json({f}).data ORDER BY price DESC', $byPriceDesc],
            'expression over a column not selected' => [
                'SELECT id FROM json({f}).data ORDER BY price * 2 DESC',
                $byPriceDesc,
            ],
            'function over a column not selected' => [
                'SELECT id FROM json({f}).data ORDER BY LOWER(name)',
                [['id' => 1], ['id' => 2], ['id' => 4], ['id' => 3]],
            ],
            'alias wins over the source column' => [
                'SELECT id, 0 - price AS price FROM json({f}).data ORDER BY price',
                [['id' => 3, 'price' => -500], ['id' => 1, 'price' => -300], ['id' => 4, 'price' => -200],
                    ['id' => 2, 'price' => -100]],
            ],
            'excluded alias wins over the source column' => [
                'SELECT id, 0 - price AS price EXCLUDE price FROM json({f}).data ORDER BY price',
                $byPriceDesc,
            ],
            'bounded sort (LIMIT)' => [
                'SELECT id FROM json({f}).data ORDER BY price DESC LIMIT 2',
                [['id' => 3], ['id' => 1]],
            ],
            'bounded sort (OFFSET + LIMIT)' => [
                'SELECT id FROM json({f}).data ORDER BY price DESC LIMIT 2 OFFSET 1',
                [['id' => 1], ['id' => 4]],
            ],
            'offset only' => [
                'SELECT id FROM json({f}).data ORDER BY price DESC OFFSET 3',
                [['id' => 2]],
            ],
            'two keys, one not selected' => [
                'SELECT id FROM json({f}).data ORDER BY cat DESC, price',
                [['id' => 2], ['id' => 4], ['id' => 1], ['id' => 3]],
            ],
            'join, joined column not selected' => [
                'SELECT id FROM json({f}).data INNER JOIN json({f}).stock AS s ON id = s.pid ORDER BY s.qty DESC',
                [['id' => 2], ['id' => 4], ['id' => 1], ['id' => 3]],
            ],
            'distinct, selected column' => [
                'SELECT DISTINCT cat FROM json({f}).data ORDER BY cat DESC',
                [['cat' => 'b'], ['cat' => 'a']],
            ],
            'distinct, star' => [
                'SELECT DISTINCT * EXCLUDE name, cat FROM json({f}).data ORDER BY price LIMIT 1',
                [['id' => 2, 'price' => 100]],
            ],
            'group by, aggregate alias' => [
                'SELECT cat, SUM(price) AS total FROM json({f}).data GROUP BY cat ORDER BY total',
                [['cat' => 'b', 'total' => 300], ['cat' => 'a', 'total' => 800]],
            ],
            'group by, excluded aggregate' => [
                'SELECT cat, SUM(price) AS total EXCLUDE total FROM json({f}).data GROUP BY cat ORDER BY total',
                [['cat' => 'b'], ['cat' => 'a']],
            ],
            'union branch' => [
                'SELECT id FROM json({f}).data WHERE cat = "a"'
                . ' UNION ALL SELECT id FROM json({f}).data WHERE cat = "b" ORDER BY price DESC',
                [['id' => 1], ['id' => 3], ['id' => 4], ['id' => 2]],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $expected
     */
    #[DataProvider('fqlCases')]
    public function testOrderBy(string $sql, array $expected): void
    {
        $this->assertSame($expected, $this->fql($sql));
    }

    public function testFluentOrderByColumnNotSelected(): void
    {
        $rows = $this->rows($this->query()->select('id')->orderBy('price')->desc());

        $this->assertSame([['id' => 3], ['id' => 1], ['id' => 4], ['id' => 2]], $rows);
    }

    public function testFluentOrderByExcludedColumnWithLimit(): void
    {
        $rows = $this->rows(
            $this->query()->select('id', 'price')->exclude('price')->orderBy('price')->desc()->limit(3)
        );

        $this->assertSame([['id' => 3], ['id' => 1], ['id' => 4]], $rows);
    }

    public function testFluentOrderByExpressionOverColumnNotSelected(): void
    {
        $rows = $this->rows($this->query()->select('id')->orderBy('0 - price * 2'));

        $this->assertSame([['id' => 3], ['id' => 1], ['id' => 4], ['id' => 2]], $rows);
    }

    public function testInMemoryResultRowsCarryNoSortKeys(): void
    {
        $result = $this->query()->select('id')->orderBy('price')->execute();

        $this->assertInstanceOf(\FQL\Results\InMemory::class, $result);
        $this->assertSame([0, 1, 2, 3], array_keys(iterator_to_array($result->getIterator())));
        $this->assertSame([['id' => 2], ['id' => 4], ['id' => 1], ['id' => 3]], iterator_to_array($result->getIterator()));
    }

    public function testStreamResultRowsCarryNoSortKeys(): void
    {
        $result = $this->query()->select('id')->orderBy('price')->execute(\FQL\Results\Stream::class);

        $this->assertSame(
            [0 => ['id' => 2], 1 => ['id' => 4], 2 => ['id' => 1], 3 => ['id' => 3]],
            iterator_to_array($result->getIterator())
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function distinctNotInOutput(): array
    {
        return [
            'column not selected' => ['SELECT DISTINCT cat FROM json({f}).data ORDER BY price'],
            'excluded column' => ['SELECT DISTINCT cat, price EXCLUDE price FROM json({f}).data ORDER BY price'],
            'expression over a column not selected' => ['SELECT DISTINCT cat FROM json({f}).data ORDER BY price * 2'],
            'excluded star column' => ['SELECT DISTINCT * EXCLUDE price FROM json({f}).data ORDER BY price'],
        ];
    }

    #[DataProvider('distinctNotInOutput')]
    public function testDistinctWithOrderByColumnNotInOutputThrows(string $sql): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('ORDER BY "price" is not in the SELECT DISTINCT output');
        $this->fql($sql);
    }

    public function testFluentDistinctWithOrderByColumnNotSelectedThrows(): void
    {
        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('ORDER BY "price" is not in the SELECT DISTINCT output');
        $this->query()->select('cat')->distinct()->orderBy('price')->execute();
    }

    public function testDistinctWithOrderByExpressionThatIsSelected(): void
    {
        $this->assertSame(
            [['LOWER(cat)' => 'b'], ['LOWER(cat)' => 'a']],
            $this->fql('SELECT DISTINCT LOWER(cat) FROM json({f}).data ORDER BY LOWER(cat) DESC')
        );
    }

    public function testExplainAnalyzeCountsTheSortPhase(): void
    {
        $rows = $this->fql('EXPLAIN ANALYZE SELECT id FROM json({f}).data WHERE price > 100 ORDER BY price DESC LIMIT 2');
        $phases = array_column($rows, null, 'phase');

        $this->assertSame(['stream', 'where', 'sort', 'limit'], array_keys($phases));
        $this->assertSame(4, $phases['stream']['rows_out']);
        $this->assertSame(3, $phases['where']['rows_out']);
        $this->assertSame(3, $phases['sort']['rows_in']);
        $this->assertSame(2, $phases['sort']['rows_out']);
        $this->assertSame(2, $phases['limit']['rows_out']);
    }
}
