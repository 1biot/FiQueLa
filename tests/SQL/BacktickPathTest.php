<?php

namespace SQL;

use FQL\Query\Provider;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage of the tokenizer's recently-added path-chain support:
 *  - backtick-escaped segments joined by `.` (`\`info\`.\`orderID\``)
 *  - mixed quoted / unquoted chains
 *  - single backtick segment whose contents contain `.` (`\`Název Zboží.cz\``)
 *  - array iteration markers `[]` between and after segments
 *
 * Each case funnels the SQL through `Sql\Provider::fql()` and into the runtime
 * evaluator to catch any regression either at tokenize-time, parse-time, or
 * resolve-time.
 */
class BacktickPathTest extends TestCase
{
    /**
     * @param array<int|string, mixed> $data
     */
    private function inMemoryJsonQuery(string $select, array $data): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fql-btp-');
        file_put_contents($tmp, json_encode($data));
        try {
            $sql = sprintf('SELECT %s FROM json(%s)', $select, $tmp);
            $results = iterator_to_array(Provider::fql($sql)->execute()->fetchAll());
            return $results;
        } finally {
            @unlink($tmp);
        }
    }

    public function testBacktickedDottedPath(): void
    {
        $rows = $this->inMemoryJsonQuery(
            '`info`.`orderID` AS `Kód`',
            [
                ['info' => ['orderID' => 'A-1']],
                ['info' => ['orderID' => 'A-2']],
            ]
        );
        $this->assertSame([['Kód' => 'A-1'], ['Kód' => 'A-2']], $rows);
    }

    public function testMixedBacktickChain(): void
    {
        $rows = $this->inMemoryJsonQuery(
            '`info`.date AS d',
            [
                ['info' => ['date' => '2024-01-01']],
                ['info' => ['date' => '2024-01-02']],
            ]
        );
        $this->assertSame([['d' => '2024-01-01'], ['d' => '2024-01-02']], $rows);
    }

    public function testBacktickKeyWithDot(): void
    {
        // Key literally contains a dot (think "Název Zboží.cz" columns in CSV).
        $rows = $this->inMemoryJsonQuery(
            '`Název Zboží.cz` AS nazev',
            [
                ['Název Zboží.cz' => 'Produkt 1'],
                ['Název Zboží.cz' => 'Produkt 2'],
            ]
        );
        $this->assertSame([['nazev' => 'Produkt 1'], ['nazev' => 'Produkt 2']], $rows);
    }

    public function testArrayAccessorIteration(): void
    {
        $rows = $this->inMemoryJsonQuery(
            'products.product[] AS items',
            [
                ['products' => ['product' => ['A', 'B', 'C']]],
            ]
        );
        $this->assertSame([['items' => ['A', 'B', 'C']]], $rows);
    }

    public function testArrayAccessorOnBacktickChain(): void
    {
        $rows = $this->inMemoryJsonQuery(
            '`products`.`product`[] AS items',
            [
                ['products' => ['product' => ['A', 'B']]],
            ]
        );
        $this->assertSame([['items' => ['A', 'B']]], $rows);
    }

    public function testLengthOfArrayPath(): void
    {
        $rows = $this->inMemoryJsonQuery(
            'LENGTH(products.product[]) AS cnt',
            [
                ['products' => ['product' => [
                    ['name' => 'A'],
                    ['name' => 'B'],
                    ['name' => 'C'],
                ]]],
            ]
        );
        $this->assertSame([['cnt' => 3]], $rows);
    }

    public function testLengthOfArrayPathWithLeafField(): void
    {
        $rows = $this->inMemoryJsonQuery(
            'LENGTH(products.product[].name) AS cnt',
            [
                ['products' => ['product' => [
                    ['name' => 'A'],
                    ['name' => 'B'],
                    ['name' => 'C'],
                ]]],
            ]
        );
        $this->assertSame([['cnt' => 3]], $rows);
    }

    public function testLengthOfBacktickedArrayPath(): void
    {
        $rows = $this->inMemoryJsonQuery(
            'LENGTH(`products`.`product`[].`name`) AS cnt',
            [
                ['products' => ['product' => [
                    ['name' => 'A'],
                    ['name' => 'B'],
                ]]],
            ]
        );
        $this->assertSame([['cnt' => 2]], $rows);
    }

    public function testImplodeOverArrayPath(): void
    {
        $rows = $this->inMemoryJsonQuery(
            'IMPLODE(products.product[].name) AS list',
            [
                ['products' => ['product' => [
                    ['name' => 'A'],
                    ['name' => 'B'],
                    ['name' => 'C'],
                ]]],
            ]
        );
        $this->assertSame([['list' => 'A,B,C']], $rows);
    }

    public function testScalarFunctionOverArrayPath(): void
    {
        $rows = $this->inMemoryJsonQuery(
            'UPPER(IMPLODE(products.product[].name)) AS list',
            [
                ['products' => ['product' => [
                    ['name' => 'a'],
                    ['name' => 'b'],
                ]]],
            ]
        );
        $this->assertSame([['list' => 'A,B']], $rows);
    }

    public function testTopLevelArrayPathStillWorks(): void
    {
        $rows = $this->inMemoryJsonQuery(
            'products.product[].name AS names',
            [
                ['products' => ['product' => [
                    ['name' => 'A'],
                    ['name' => 'B'],
                ]]],
            ]
        );
        $this->assertSame([['names' => ['A', 'B']]], $rows);
    }

    public function testAliasWithDotInBackticks(): void
    {
        // The alias must come out WITHOUT backticks.
        $rows = $this->inMemoryJsonQuery(
            'name AS `zbozi.cz`',
            [['name' => 'foo'], ['name' => 'bar']]
        );
        $this->assertSame([['zbozi.cz' => 'foo'], ['zbozi.cz' => 'bar']], $rows);
    }

    /**
     * Function arguments are rendered back to FQL and parsed again; a backticked column
     * whose name has a colon, a space or diacritics lost its backticks there and the
     * whole call evaluated to NULL.
     */
    public function testFunctionOverBacktickedColumnWithColonAndDiacritics(): void
    {
        $rows = $this->inMemoryJsonQuery(
            'CONCAT(name, ": ", `variant:Rozmer uteráku`) AS joined, LOWER(`a b`) AS lowered',
            [
                ['name' => 'Uterák', 'variant:Rozmer uteráku' => '30x50cm', 'a b' => 'XY'],
                ['name' => 'Osuška', 'variant:Rozmer uteráku' => '70x140cm', 'a b' => 'Z'],
            ]
        );
        $this->assertSame([
            ['joined' => 'Uterák: 30x50cm', 'lowered' => 'xy'],
            ['joined' => 'Osuška: 70x140cm', 'lowered' => 'z'],
        ], $rows);
    }

    public function testUnaliasedFunctionOverBacktickedColumnKeepsResultKey(): void
    {
        $rows = $this->inMemoryJsonQuery('`a:b`, UPPER(`a:b`)', [['a:b' => 'x']]);
        $this->assertSame([['a:b' => 'x', 'UPPER(a:b)' => 'X']], $rows);
    }

    public function testOrderByAndGroupByFunctionOverBacktickedColumn(): void
    {
        $data = [
            ['name' => 'X', 'a:b' => 'cc'],
            ['name' => 'Y', 'a:b' => 'AA'],
            ['name' => 'Z', 'a:b' => 'aa'],
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'fql-btp-');
        file_put_contents($tmp, json_encode($data));
        try {
            $sorted = iterator_to_array(Provider::fql(
                sprintf('SELECT name FROM json(%s) ORDER BY LOWER(`a:b`) DESC, name ASC', $tmp)
            )->execute()->fetchAll());
            $this->assertSame(['X', 'Y', 'Z'], array_column($sorted, 'name'));

            $grouped = iterator_to_array(Provider::fql(sprintf(
                'SELECT LOWER(`a:b`) AS k, COUNT(name) AS cnt FROM json(%s) GROUP BY LOWER(`a:b`) ORDER BY k',
                $tmp
            ))->execute()->fetchAll());
            $this->assertSame([['k' => 'aa', 'cnt' => 2], ['k' => 'cc', 'cnt' => 1]], $grouped);
        } finally {
            @unlink($tmp);
        }
    }

    public function testStringLiteralWithDoubleQuoteInsideFunction(): void
    {
        $rows = $this->inMemoryJsonQuery("CONCAT(name, ' \"', name, '\"') AS r", [['name' => 'a']]);
        $this->assertSame([['r' => 'a "a"']], $rows);
    }

    public function testCollectObjectAliasWithSpace(): void
    {
        $rows = $this->inMemoryJsonQuery(
            'COLLECT_OBJECT(`a:b` AS `my key`) AS items',
            [['a:b' => 1], ['a:b' => 2]]
        );
        $this->assertSame([['items' => [['my key' => 1], ['my key' => 2]]]], $rows);
    }
}
