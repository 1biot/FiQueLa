<?php

namespace SQL\Formatter;

use FQL\Sql\Formatter\FormatterOptions;
use FQL\Sql\Formatter\SqlFormatter;
use FQL\Sql\Provider as SqlProvider;
use PHPUnit\Framework\TestCase;

class SqlFormatterTest extends TestCase
{
    private SqlFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new SqlFormatter();
    }

    public function testFormatsSingleFieldInline(): void
    {
        $out = $this->formatter->format('select id from x');
        $this->assertSame("SELECT id\nFROM x", $out);
    }

    public function testFormatsMultipleFieldsOnSeparateLines(): void
    {
        $out = $this->formatter->format('select id, name, price from x');
        $this->assertStringContainsString("SELECT\n    id,\n    name,\n    price", $out);
    }

    public function testEmitsWhereClause(): void
    {
        $out = $this->formatter->format('select * from x where a = 1 and b > 2');
        $this->assertStringContainsString('WHERE', $out);
        $this->assertStringContainsString(" AND ", $out);
    }

    public function testEmitsGroupByAndHaving(): void
    {
        $out = $this->formatter->format('select brand.name, count(id) from x group by brand.name having count(id) > 1');
        $this->assertStringContainsString('GROUP BY brand.name', $out);
        $this->assertStringContainsString('HAVING', $out);
    }

    public function testEmitsOrderByWithDirection(): void
    {
        $out = $this->formatter->format('select id from x order by price desc, name asc');
        $this->assertStringContainsString('ORDER BY price DESC, name ASC', $out);
    }

    public function testEmitsLimitAndOffset(): void
    {
        $out = $this->formatter->format('select id from x limit 5 offset 10');
        $this->assertStringContainsString('LIMIT 5 OFFSET 10', $out);
    }

    public function testEmitsInto(): void
    {
        $out = $this->formatter->format('select id into csv(out.csv) from x');
        $this->assertStringContainsString('INTO csv(out.csv)', $out);
    }

    public function testEmitsDistinct(): void
    {
        $out = $this->formatter->format('select distinct id from x');
        $this->assertStringContainsString('SELECT DISTINCT id', $out);
    }

    public function testEmitsExcludeField(): void
    {
        $out = $this->formatter->format('select *, exclude description from x');
        $this->assertStringContainsString('EXCLUDE description', $out);
    }

    public function testEmitsJoinClauses(): void
    {
        $out = $this->formatter->format(
            'select id from x inner join y as y2 on x.id = y2.id'
        );
        $this->assertStringContainsString('INNER JOIN', $out);
        $this->assertStringContainsString(' AS y2 ON ', $out);
    }

    public function testEmitsLeftRightFullJoins(): void
    {
        $variants = ['LEFT', 'RIGHT', 'FULL'];
        foreach ($variants as $v) {
            $out = $this->formatter->format(
                sprintf('select id from x %s outer join y as y2 on x.id = y2.id', $v)
            );
            $this->assertStringContainsString("$v JOIN", $out);
        }
    }

    public function testEmitsUnionAndUnionAll(): void
    {
        $out = $this->formatter->format('select id from x union all select id from y');
        $this->assertStringContainsString('UNION ALL', $out);

        $out = $this->formatter->format('select id from x union select id from y');
        $this->assertStringContainsString("\nUNION\n", $out);
    }

    public function testEmitsDescribeStatement(): void
    {
        $out = $this->formatter->format('describe json(x.json)');
        $this->assertSame('DESCRIBE json(x.json)', $out);
    }

    public function testEmitsExplainAndExplainAnalyze(): void
    {
        $out = $this->formatter->format('explain select id from x');
        $this->assertStringStartsWith('EXPLAIN', $out);

        $out = $this->formatter->format('explain analyze select id from x');
        $this->assertStringStartsWith('EXPLAIN ANALYZE', $out);
    }

    public function testEmitsSubqueryJoin(): void
    {
        $out = $this->formatter->format(
            'select id from x left join (select id from y) as y2 on x.id = y2.id'
        );
        $this->assertStringContainsString('LEFT JOIN (', $out);
        $this->assertStringContainsString('SELECT id', $out);
        $this->assertStringContainsString('FROM y', $out);
    }

    public function testFromAliasIsRendered(): void
    {
        $out = $this->formatter->format('select id from data.products as p');
        $this->assertStringContainsString('FROM data.products AS p', $out);
    }

    public function testLowercaseKeywordsOption(): void
    {
        $formatter = new SqlFormatter(new FormatterOptions(uppercaseKeywords: false));
        $out = $formatter->format('select id from x');
        $this->assertStringContainsString('select id', $out);
        $this->assertStringContainsString('from x', $out);
    }

    public function testSingleLineFieldsWhenDisabled(): void
    {
        $formatter = new SqlFormatter(new FormatterOptions(fieldsPerLine: false));
        $out = $formatter->format('select id, name, price from x');
        $this->assertStringContainsString('SELECT id, name, price', $out);
        $this->assertStringNotContainsString("\n    id,", $out);
    }

    public function testCustomIndent(): void
    {
        $formatter = new SqlFormatter(new FormatterOptions(indent: "\t"));
        $out = $formatter->format('select id, name from x');
        $this->assertStringContainsString("\n\tid,\n\tname", $out);
    }

    public function testProviderFormatMatchesDirectFormatter(): void
    {
        $sql = 'select id, name from x where price > 10';
        $this->assertSame(
            (new SqlFormatter())->format($sql),
            SqlProvider::format($sql)
        );
    }

    public function testProviderFormatAcceptsCustomOptions(): void
    {
        $sql = 'select id from x';
        $options = new FormatterOptions(uppercaseKeywords: false);
        $this->assertStringContainsString(
            'select id',
            SqlProvider::format($sql, $options)
        );
    }
}
