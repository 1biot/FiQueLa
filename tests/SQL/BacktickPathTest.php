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

    public function testAliasWithDotInBackticks(): void
    {
        // The alias must come out WITHOUT backticks.
        $rows = $this->inMemoryJsonQuery(
            'name AS `zbozi.cz`',
            [['name' => 'foo'], ['name' => 'bar']]
        );
        $this->assertSame([['zbozi.cz' => 'foo'], ['zbozi.cz' => 'bar']], $rows);
    }
}
