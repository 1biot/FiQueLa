<?php

namespace SQL;

use FQL\Query\Provider;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Provider as SqlProvider;
use PHPUnit\Framework\TestCase;

/**
 * Files whose path holds characters an unquoted file query does not allow
 * (parentheses, brackets, commas, …) are read, joined, described and written through
 * a quoted path: `csv("my data (2026)/products [v1].csv", delimiter: ";")`.
 */
class QuotedFilePathTest extends TestCase
{
    private const PRODUCTS = 'csv("my data (2026)/products [v1].csv", "utf-8", ";")';
    private const PRODUCTS_NAMED = 'csv("my data (2026)/products [v1].csv", delimiter: ";")';
    private const PRICES = 'jsonFile("my data (2026)/prices, (copy).json")';

    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/fiquela-quoted-' . uniqid();
        mkdir($this->basePath . '/my data (2026)', 0775, true);
        file_put_contents($this->basePath . '/my data (2026)/products [v1].csv', "id;name\n1;Alpha\n2;Beta\n");
        file_put_contents(
            $this->basePath . '/my data (2026)/prices, (copy).json',
            '[{"id": "1", "price": 10}, {"id": "2", "price": 20}]'
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);
    }

    public function testFromWithPositionalAndNamedParameters(): void
    {
        $expected = [['id' => '1', 'name' => 'Alpha'], ['id' => '2', 'name' => 'Beta']];
        $singleQuoted = [
            str_replace('"my data (2026)/products [v1].csv"', "'my data (2026)/products [v1].csv'", self::PRODUCTS),
            str_replace('"my data (2026)/products [v1].csv"', "'my data (2026)/products [v1].csv'", self::PRODUCTS_NAMED),
        ];

        foreach ([self::PRODUCTS, self::PRODUCTS_NAMED, ...$singleQuoted] as $source) {
            $query = SqlProvider::compile(sprintf('SELECT id, name FROM %s.*', $source), $this->basePath)->toQuery();
            $this->assertSame($expected, iterator_to_array($query->execute()->getIterator(), false), $source);
        }
    }

    public function testBacktickedPathIsRejected(): void
    {
        $this->expectException(ParseException::class);
        SqlProvider::compile('SELECT id FROM csv(`my data (2026)/products [v1].csv`).*', $this->basePath)->toQuery();
    }

    public function testFromFileQuery(): void
    {
        $cwd = getcwd();
        $this->assertIsString($cwd);
        chdir($this->basePath);
        try {
            $rows = Provider::fromFileQuery(self::PRODUCTS_NAMED . '.*')->select('name')->execute()->getIterator();
            $this->assertSame([['name' => 'Alpha'], ['name' => 'Beta']], iterator_to_array($rows, false));
        } finally {
            chdir($cwd);
        }
    }

    public function testJoinExplainAndToString(): void
    {
        $sql = sprintf(
            'SELECT id, name, p.price FROM %s.* JOIN %s.* AS p ON id = p.id',
            self::PRODUCTS_NAMED,
            self::PRICES
        );
        $query = SqlProvider::compile($sql, $this->basePath)->toQuery();

        $this->assertSame(
            [['id' => '1', 'name' => 'Alpha', 'p.price' => 10], ['id' => '2', 'name' => 'Beta', 'p.price' => 20]],
            iterator_to_array($query->execute()->getIterator(), false)
        );

        // the stream sources are written back quoted, so the query stays printable
        $this->assertStringContainsString('FROM csv("products [v1].csv", "utf-8", ";").*', (string) $query);
        $this->assertStringContainsString('JOIN json("prices, (copy).json").* AS p', (string) $query);

        $explain = SqlProvider::compile('EXPLAIN ' . $sql, $this->basePath)->toQuery();
        $notes = array_column(iterator_to_array($explain->execute()->getIterator(), false), 'note');
        $this->assertContains('read source csv("products [v1].csv", "utf-8", ";")', $notes);
    }

    public function testDescribe(): void
    {
        $query = SqlProvider::compile(sprintf('DESCRIBE %s', self::PRODUCTS), $this->basePath)->toQuery();

        $columns = array_column(iterator_to_array($query->execute()->getIterator(), false), 'column');
        $this->assertSame(['id', 'name'], $columns);
    }

    public function testInto(): void
    {
        $sql = sprintf('SELECT id, name FROM %s.* INTO csv("out (x)/result [1].csv", delimiter: "|")', self::PRODUCTS);
        $query = SqlProvider::compile($sql, $this->basePath)->toQuery();

        $into = $query->getInto();
        $this->assertNotNull($into);
        $this->assertSame(realpath($this->basePath) . '/out (x)/result [1].csv', $into->file);

        $query->execute()->into($into);
        $this->assertSame("id|name\n1|Alpha\n2|Beta\n", file_get_contents($this->basePath . '/out (x)/result [1].csv'));
    }

    public function testLintFindsTheQuotedFile(): void
    {
        $report = SqlProvider::lint(sprintf('SELECT id FROM %s.*', self::PRODUCTS), true, $this->basePath);
        $this->assertSame([], $report->issues);

        $missing = SqlProvider::lint('SELECT id FROM csv("my data (2026)/missing (1).csv").*', true, $this->basePath);
        $this->assertSame(['file-not-found'], array_map(static fn ($issue) => $issue->rule, $missing->issues));
    }

    public function testFormatterKeepsTheQuotedPath(): void
    {
        $this->assertSame(
            "SELECT id\nFROM " . self::PRODUCTS_NAMED . '.*',
            SqlProvider::format('select id from ' . self::PRODUCTS_NAMED . '.*')
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($itemPath) ? $this->removeDirectory($itemPath) : unlink($itemPath);
        }
        rmdir($path);
    }
}
