<?php

namespace Traits;

use FQL\Enum\Operator;
use FQL\Enum\Type;
use FQL\Exception\QueryLogicException;
use FQL\Interface\Query;
use FQL\Sql\Provider as SqlProvider;
use FQL\Stream\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The first argument of fluent where() / having() is an SQL expression: a plain
 * field path keeps the SimpleCondition lookup, anything else (function call,
 * arithmetic, CASE, …) evaluates like the same condition written in FQL.
 */
class ConditionsExpressionTest extends TestCase
{
    private const DATA = '{"data":['
        . '{"id":1,"price":300,"cat":"a","name":"Alpha","my-field":"x","items":[{"price":5}],"Název zboží":"Kolo"},'
        . '{"id":2,"price":100,"cat":"b","name":"Beta","my-field":"y","items":[{"price":7}],"Název zboží":"Auto"},'
        . '{"id":3,"price":500,"cat":"a","name":"Gamma","my-field":"x","items":[{"price":9}],"Název zboží":"Vlak"},'
        . '{"id":4,"price":200,"cat":"b","name":null,"my-field":"z","items":[{"price":1}],"Název zboží":"Loď"}'
        . ']}';

    private static ?string $file = null;

    public static function setUpBeforeClass(): void
    {
        self::$file = tempnam(sys_get_temp_dir(), 'fql-cond-expr-') . '.json';
        file_put_contents(self::$file, self::DATA);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$file !== null) {
            @unlink(self::$file);
        }
    }

    private function query(): Query
    {
        return Json::string(self::DATA)->query()->from('data');
    }

    /**
     * @return int[]
     */
    private function ids(Query $query): array
    {
        return array_column(iterator_to_array($query->execute()->fetchAll()), 'id');
    }

    /**
     * @return int[]
     */
    private function fqlIds(string $where): array
    {
        $sql = sprintf('SELECT id FROM json(%s).data %s', self::$file, $where);
        return array_column(iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll()), 'id');
    }

    /**
     * @return array<string, array{string, Operator, int|float|string|Type|array<int|float|string>, string, int[]}>
     */
    public static function expressionConditions(): array
    {
        return [
            'function' => ['LOWER(name)', Operator::EQUAL, 'alpha', 'WHERE LOWER(name) = "alpha"', [1]],
            'arithmetic' => ['price * 2', Operator::GREATER_THAN, 500, 'WHERE price * 2 > 500', [1, 3]],
            'nested function' => ['ROUND(price / 3, 0)', Operator::EQUAL, 100, 'WHERE ROUND(price / 3, 0) = 100', [1]],
            'like' => ['LOWER(name)', Operator::LIKE, '%a', 'WHERE LOWER(name) LIKE "%a"', [1, 2, 3]],
            'in' => ['UPPER(cat)', Operator::IN, ['A', 'C'], 'WHERE UPPER(cat) IN ("A", "C")', [1, 3]],
            'between' => ['price + 50', Operator::BETWEEN, [200, 350], 'WHERE price + 50 BETWEEN 200 AND 350', [1, 4]],
            'is null' => ['LOWER(name)', Operator::IS, Type::NULL, 'WHERE LOWER(name) IS NULL', [4]],
            'case' => [
                'CASE WHEN price > 250 THEN "high" ELSE "low" END',
                Operator::EQUAL,
                'high',
                'WHERE CASE WHEN price > 250 THEN "high" ELSE "low" END = "high"',
                [1, 3],
            ],
            'column on the right' => ['LOWER(cat)', Operator::EQUAL, '`cat`', 'WHERE LOWER(cat) = cat', [1, 2, 3, 4]],
        ];
    }

    /**
     * @param int|float|string|Type|array<int|float|string> $value
     * @param int[] $expected
     */
    #[DataProvider('expressionConditions')]
    public function testWhereExpressionMatchesFql(
        string $field,
        Operator $operator,
        int|float|string|Type|array $value,
        string $fqlWhere,
        array $expected
    ): void {
        $fluent = $this->ids($this->query()->select('id')->where($field, $operator, $value));

        $this->assertSame($expected, $fluent);
        $this->assertSame($this->fqlIds($fqlWhere), $fluent);
    }

    /**
     * @param int|float|string|Type|array<int|float|string> $value
     * @param int[] $expected
     */
    #[DataProvider('expressionConditions')]
    public function testWhereExpressionRoundTripsThroughFql(
        string $field,
        Operator $operator,
        int|float|string|Type|array $value,
        string $fqlWhere,
        array $expected
    ): void {
        $sql = sprintf('SELECT id FROM json(%s).data %s', self::$file, $fqlWhere);
        $fromFql = (string) SqlProvider::compile($sql)->toQuery();

        $fluent = (string) \FQL\Query\Provider::fromFileQuery(sprintf('json(%s).data', self::$file))
            ->select('id')
            ->where($field, $operator, $value);

        // The stream source prints the file name only; compile it against its directory.
        $reparsed = SqlProvider::compile($fluent, dirname((string) self::$file))->toQuery();
        $this->assertSame($fromFql, $fluent);
        $this->assertSame($fromFql, (string) $reparsed);
        $this->assertSame($expected, $this->ids($reparsed));
    }

    public function testRenderedExpressionCondition(): void
    {
        $sql = (string) $this->query()
            ->select('id')
            ->where('LOWER(name)', Operator::LIKE, 'al%')
            ->and('price * 2', Operator::IN, [600, 1000])
            ->and('UPPER(cat)', Operator::NOT_IN, ["it's"]);

        $this->assertStringContainsString(
            "WHERE LOWER(name) LIKE \"al%\" AND price * 2 IN (600, 1000) AND UPPER(cat) NOT IN (\"it's\")",
            preg_replace('/\s+/', ' ', $sql) ?? ''
        );
    }

    public function testQuotedValueIsTheLiteralBetweenTheQuotes(): void
    {
        $this->assertSame(
            [1],
            $this->ids($this->query()->select('id')->where('lower(name)', Operator::EQUAL, '"alpha"'))
        );
        $this->assertSame([1], $this->ids($this->query()->select('id')->where('LOWER(name)', Operator::LIKE, "'al%'")));
    }

    public function testUnprintableValueFallsBackToCompilerRendering(): void
    {
        // A string with both quote characters has no FQL spelling; the condition
        // still renders (for debugging) and evaluates.
        $value = 'it\'s "x"';
        $query = $this->query()->select('id')->where('LOWER(name)', Operator::IN, [$value, 'beta']);

        $this->assertSame([2], $this->ids($query));
        $condition = new \FQL\Conditions\ExpressionCondition(
            \FQL\Enum\LogicalOperator::AND,
            SqlProvider::parseExpression('LOWER(name)'),
            Operator::EQUAL,
            new \FQL\Sql\Ast\Expression\LiteralNode($value, Type::STRING, '', \FQL\Sql\Token\Position::synthetic())
        );
        $this->assertStringStartsWith('LOWER(name) = ', $condition->render());
    }

    public function testHavingExpressionWithoutGroupBy(): void
    {
        $query = $this->query()->select('id', 'name')->having('LOWER(name)', Operator::EQUAL, 'gamma');

        $this->assertSame([3], $this->ids($query));
    }

    public function testHavingAggregateExpressionThrowsLikeFql(): void
    {
        $fql = sprintf(
            'SELECT cat, SUM(price * 2) AS total FROM json(%s).data GROUP BY cat HAVING SUM(price * 2) > 1000',
            self::$file
        );
        try {
            iterator_to_array(SqlProvider::compile($fql)->toQuery()->execute()->fetchAll());
            $this->fail('FQL is expected to throw');
        } catch (QueryLogicException $e) {
            $fqlMessage = $e->getMessage();
        }

        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage($fqlMessage);
        iterator_to_array(
            $this->query()
                ->select('cat')
                ->sum('price * 2')->as('total')
                ->groupBy('cat')
                ->having('SUM(price * 2)', Operator::GREATER_THAN, 1000)
                ->execute()
                ->fetchAll()
        );
    }

    public function testHavingOnUnaliasedSelectedAggregateKeepsWorking(): void
    {
        // The output column is literally named "SUM(price)", so the condition still
        // reads that column (as in 3.4.0) instead of evaluating SUM row by row.
        $rows = iterator_to_array(
            $this->query()
                ->select('cat')
                ->sum('price')
                ->groupBy('cat')
                ->having('SUM(price)', Operator::GREATER_THAN, 500)
                ->execute()
                ->fetchAll()
        );

        $this->assertSame([['cat' => 'a', 'SUM(price)' => 800]], $rows);
    }

    /**
     * @return array<string, array{string, Operator, string|int, int[]}>
     */
    public static function plainPaths(): array
    {
        return [
            'dashed field' => ['my-field', Operator::EQUAL, 'x', [1, 3]],
            'array index path' => ['items.0.price', Operator::GREATER_THAN, 6, [2, 3]],
            'backticked field' => ['`Název zboží`', Operator::EQUAL, 'Kolo', [1]],
            'field with spaces' => ['Název zboží', Operator::EQUAL, 'Auto', [2]],
            'nested path' => ['name', Operator::LIKE, '%ta', [2]],
        ];
    }

    /**
     * @param int[] $expected
     */
    #[DataProvider('plainPaths')]
    public function testPlainPathsKeepWorking(
        string $field,
        Operator $operator,
        string|int $value,
        array $expected
    ): void
    {
        $this->assertSame($expected, $this->ids($this->query()->select('id')->where($field, $operator, $value)));
    }

    public function testRightSideStringNamingAFieldStaysAFieldForPlainPaths(): void
    {
        // SimpleCondition reads a string value as a field when the row has one.
        $this->assertSame(
            [1, 2, 3, 4],
            $this->ids($this->query()->select('id')->where('cat', Operator::EQUAL, 'cat'))
        );
    }

    public function testFqlRenderingOfExpressionConditions(): void
    {
        $sql = sprintf(
            "SELECT id FROM json(%s).data WHERE LOWER(name) LIKE 'al%%' AND UPPER(cat) IN ('A', 'B')"
            . " AND name REGEXP '^A' AND price * 2 > 1",
            self::$file
        );
        $rendered = preg_replace('/\s+/', ' ', (string) SqlProvider::compile($sql)->toQuery()) ?? '';

        $this->assertStringContainsString(
            "WHERE LOWER(name) LIKE 'al%' AND UPPER(cat) IN ('A', 'B') AND name REGEXP '^A' AND price * 2 > 1",
            $rendered
        );
    }
}
