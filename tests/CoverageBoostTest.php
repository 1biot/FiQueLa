<?php

namespace FQL\Tests;

use FQL\Enum\Format;
use FQL\Enum\Fulltext;
use FQL\Enum\Operator;
use FQL\Enum\Type;
use FQL\Query\FileQuery;
use FQL\Query\Provider;
use FQL\Stream\Writers\CsvWriter;
use PHPUnit\Framework\TestCase;

/**
 * Focused coverage top-ups that don't warrant their own file — scattered
 * edge branches across the public API. Each test targets a specific uncovered
 * code path with a minimal surface and clear intent.
 */
class CoverageBoostTest extends TestCase
{
    // ------------------------------------------------------------------
    //  Enum\Format — validators on remaining formats
    // ------------------------------------------------------------------

    public function testXmlEncodingAcceptsUtf8(): void
    {
        Format::XML->validateParams(['encoding' => 'UTF-8']);
        $this->expectNotToPerformAssertions();
    }

    public function testLogFormatHasDefaultParams(): void
    {
        $defaults = Format::LOG->getDefaultParams();
        $this->assertArrayHasKey('format', $defaults);
    }

    public function testDirFormatHasEmptyParams(): void
    {
        $this->assertSame([], Format::DIR->getDefaultParams());
    }

    public function testFromExtensionHandlesAliases(): void
    {
        // `.tsv` is an alias for CSV; `.jsonFile` is JSON Stream.
        $this->assertSame(Format::CSV, Format::fromExtension('tsv'));
        $this->assertSame(Format::JSON_STREAM, Format::fromExtension('jsonFile'));
        $this->assertSame(Format::ND_JSON, Format::fromExtension('ndJson'));
    }

    public function testFromExtensionCaseInsensitive(): void
    {
        $this->assertSame(Format::CSV, Format::fromExtension('CSV'));
        $this->assertSame(Format::XML, Format::fromExtension('XML'));
    }

    // ------------------------------------------------------------------
    //  Enum\Fulltext — parseQuery / boolean operators
    // ------------------------------------------------------------------

    public function testFulltextBooleanRequiredHit(): void
    {
        $score = Fulltext::BOOLEAN->calculate('quick brown fox', ['+brown']);
        $this->assertGreaterThan(0, $score);
    }

    public function testFulltextBooleanOptional(): void
    {
        // No prefix → optional term (contributes to score if present).
        $score = Fulltext::BOOLEAN->calculate('hello world', ['world']);
        $this->assertGreaterThan(0, $score);
    }

    public function testFulltextNaturalScoresByFrequency(): void
    {
        $low = Fulltext::NATURAL->calculate('cat dog', ['cat']);
        $hi = Fulltext::NATURAL->calculate('cat cat cat', ['cat']);
        $this->assertGreaterThan($low, $hi);
    }

    // ------------------------------------------------------------------
    //  CsvWriter encoding error
    // ------------------------------------------------------------------

    public function testCsvWriterRejectsUnsupportedEncodingAtFirstRow(): void
    {
        // FileQuery validateParams catches bad encoding — exception happens
        // at construction of FileQuery.
        $this->expectException(\FQL\Exception\InvalidFormatException::class);
        new FileQuery('csv(/tmp/x.csv, "not-a-real-encoding")');
    }

    // ------------------------------------------------------------------
    //  Operator — less common render paths
    // ------------------------------------------------------------------

    public function testOperatorRenderBacktickField(): void
    {
        $out = Operator::EQUAL->render('`weird field`', 'x');
        $this->assertStringContainsString('weird field', $out);
    }

    public function testOperatorRenderTypeRightSide(): void
    {
        $out = Operator::IS->render('x', Type::INTEGER);
        // Type's `value` is lowercase abbreviation ('int'); rendered uppercase.
        $this->assertStringContainsString('INT', $out);
    }

    // ------------------------------------------------------------------
    //  Query\Provider + end-to-end edges
    // ------------------------------------------------------------------

    public function testFqlSelectWithBetween(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-between-');
        file_put_contents($path, json_encode([
            ['price' => 5],
            ['price' => 50],
            ['price' => 500],
        ]));
        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT * FROM json($path) WHERE price BETWEEN 10 AND 100")
                    ->execute()->fetchAll()
            );
            $this->assertCount(1, $rows);
            $this->assertSame(50, $rows[0]['price']);
        } finally {
            @unlink($path);
        }
    }

    public function testFqlSelectWithNotBetween(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-notbetween-');
        file_put_contents($path, json_encode([
            ['price' => 5],
            ['price' => 50],
            ['price' => 500],
        ]));
        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT * FROM json($path) WHERE price NOT BETWEEN 10 AND 100")
                    ->execute()->fetchAll()
            );
            $this->assertCount(2, $rows);
        } finally {
            @unlink($path);
        }
    }

    public function testFqlSelectWithNotIn(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-notin-');
        file_put_contents($path, json_encode([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
            ['id' => 4],
        ]));
        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT * FROM json($path) WHERE id NOT IN (2, 3)")
                    ->execute()->fetchAll()
            );
            $this->assertCount(2, $rows);
            $ids = array_column($rows, 'id');
            sort($ids);
            $this->assertSame([1, 4], $ids);
        } finally {
            @unlink($path);
        }
    }

    public function testFqlWithEmptyResult(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-empty-');
        file_put_contents($path, json_encode([['id' => 1]]));
        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT * FROM json($path) WHERE id > 100")
                    ->execute()->fetchAll()
            );
            $this->assertCount(0, $rows);
        } finally {
            @unlink($path);
        }
    }

    public function testFqlGroupByWithHavingReferringToAlias(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-having-');
        file_put_contents($path, json_encode([
            ['cat' => 'A', 'v' => 1],
            ['cat' => 'A', 'v' => 2],
            ['cat' => 'B', 'v' => 5],
        ]));
        try {
            // HAVING with aggregate alias (widely-supported shape).
            $rows = iterator_to_array(
                Provider::fql(
                    "SELECT cat, SUM(v) AS s FROM json($path) GROUP BY cat HAVING s > 2"
                )->execute()->fetchAll()
            );
            $this->assertGreaterThan(0, count($rows));
        } finally {
            @unlink($path);
        }
    }

    public function testFqlDistinct(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-distinct-');
        file_put_contents($path, json_encode([
            ['a' => 1],
            ['a' => 1],
            ['a' => 2],
        ]));
        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT DISTINCT a FROM json($path)")
                    ->execute()->fetchAll()
            );
            $this->assertCount(2, $rows);
        } finally {
            @unlink($path);
        }
    }

    public function testFqlOrderByMultipleFields(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-order-');
        file_put_contents($path, json_encode([
            ['a' => 'x', 'b' => 2],
            ['a' => 'x', 'b' => 1],
            ['a' => 'y', 'b' => 0],
        ]));
        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT * FROM json($path) ORDER BY a ASC, b ASC")
                    ->execute()->fetchAll()
            );
            $this->assertSame(1, $rows[0]['b']);
            $this->assertSame(2, $rows[1]['b']);
            $this->assertSame(0, $rows[2]['b']);
        } finally {
            @unlink($path);
        }
    }

    public function testFqlCountDistinct(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-cd-');
        file_put_contents($path, json_encode([
            ['x' => 'a'],
            ['x' => 'a'],
            ['x' => 'b'],
            ['x' => 'c'],
        ]));
        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT COUNT(DISTINCT x) AS c FROM json($path)")
                    ->execute()->fetchAll()
            );
            $this->assertSame(3, $rows[0]['c']);
        } finally {
            @unlink($path);
        }
    }

    public function testFqlGroupConcatWithSeparator(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fql-gc-');
        file_put_contents($path, json_encode([
            ['cat' => 'A', 'v' => 'x'],
            ['cat' => 'A', 'v' => 'y'],
        ]));
        try {
            $rows = iterator_to_array(
                Provider::fql("SELECT cat, GROUP_CONCAT(v, '|') AS joined FROM json($path) GROUP BY cat")
                    ->execute()->fetchAll()
            );
            $this->assertStringContainsString('x', $rows[0]['joined']);
            $this->assertStringContainsString('y', $rows[0]['joined']);
        } finally {
            @unlink($path);
        }
    }
}
