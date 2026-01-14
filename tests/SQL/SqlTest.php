<?php

namespace SQL;

use FQL\Functions\Aggregate\Avg;
use FQL\Functions\Aggregate\Count;
use FQL\Functions\Aggregate\GroupConcat;
use FQL\Functions\Aggregate\Max;
use FQL\Functions\Aggregate\Min;
use FQL\Functions\Aggregate\Sum;
use FQL\Functions\Hashing\Md5;
use FQL\Functions\Hashing\Sha1;
use FQL\Functions\Math\Ceil;
use FQL\Functions\Math\Floor;
use FQL\Functions\Math\Mod;
use FQL\Functions\Math\Round;
use FQL\Functions\String\Base64Decode;
use FQL\Functions\String\Base64Encode;
use FQL\Functions\String\Concat;
use FQL\Functions\String\ConcatWS;
use FQL\Functions\String\Explode;
use FQL\Functions\String\Fulltext;
use FQL\Functions\String\Implode;
use FQL\Functions\String\LeftPad;
use FQL\Functions\String\Locate;
use FQL\Functions\String\Lower;
use FQL\Functions\String\RandomString;
use FQL\Functions\String\Replace;
use FQL\Functions\String\Reverse;
use FQL\Functions\String\RightPad;
use FQL\Functions\String\Substring;
use FQL\Functions\String\Upper;
use FQL\Functions\Utils\ArrayCombine;
use FQL\Functions\Utils\ArrayFilter;
use FQL\Functions\Utils\ArrayMerge;
use FQL\Functions\Utils\ColSplit;
use FQL\Functions\Utils\Coalesce;
use FQL\Functions\Utils\CoalesceNotEmpty;
use FQL\Functions\Utils\CurrentDate;
use FQL\Functions\Utils\CurrentTime;
use FQL\Functions\Utils\CurrentTimestamp;
use FQL\Functions\Utils\DateDiff;
use FQL\Functions\Utils\DateFormat;
use FQL\Functions\Utils\Length;
use FQL\Functions\Utils\Now;
use FQL\Functions\Utils\RandomBytes;
use FQL\Functions\Utils\SelectIf;
use FQL\Functions\Utils\SelectIfNull;
use FQL\Functions\Utils\SelectIsNull;
use FQL\Query\TestProvider;
use FQL\Sql\Sql;
use PHPUnit\Framework\TestCase;

class SqlTest extends TestCase
{
    /**
     * @dataProvider functionProvider
     */
    public function testFunctionParsing(string $sql, string $expectedKey, string $expectedClass): void
    {
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery(new TestProvider());

        $selectedFields = $query->getSelectedFields();
        $this->assertArrayHasKey($expectedKey, $selectedFields);
        $this->assertSame($expectedKey, $selectedFields[$expectedKey]['originField']);
        $this->assertInstanceOf($expectedClass, $selectedFields[$expectedKey]['function']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: class-string}>
     */
    public static function functionProvider(): array
    {
        return [
            'avg' => ['SELECT AVG(price)', 'AVG(price)', Avg::class],
            'count' => ['SELECT COUNT(id)', 'COUNT(id)', Count::class],
            'group concat' => ['SELECT GROUP_CONCAT(name, "|")', 'GROUP_CONCAT(name, "|")', GroupConcat::class],
            'max' => ['SELECT MAX(price)', 'MAX(price)', Max::class],
            'min' => ['SELECT MIN(price)', 'MIN(price)', Min::class],
            'sum' => ['SELECT SUM(price)', 'SUM(price)', Sum::class],
            'md5' => ['SELECT MD5(name)', 'MD5(name)', Md5::class],
            'sha1' => ['SELECT SHA1(name)', 'SHA1(name)', Sha1::class],
            'ceil' => ['SELECT CEIL(price)', 'CEIL(price)', Ceil::class],
            'floor' => ['SELECT FLOOR(price)', 'FLOOR(price)', Floor::class],
            'mod' => ['SELECT MOD(price, 2)', 'MOD(price, 2)', Mod::class],
            'round' => ['SELECT ROUND(price, 1)', 'ROUND(price, 1)', Round::class],
            'base64 decode' => ['SELECT BASE64_DECODE(value)', 'BASE64_DECODE(value)', Base64Decode::class],
            'base64 encode' => ['SELECT BASE64_ENCODE(value)', 'BASE64_ENCODE(value)', Base64Encode::class],
            'concat' => ['SELECT CONCAT(first, second)', 'CONCAT(first, second)', Concat::class],
            'concat ws' => ['SELECT CONCAT_WS("-", first, second)', 'CONCAT_WS("-", first, second)', ConcatWS::class],
            'explode' => ['SELECT EXPLODE(name, " ")', 'EXPLODE(name, " ")', Explode::class],
            'implode' => ['SELECT IMPLODE(tags, ",")', 'IMPLODE(tags, ",")', Implode::class],
            'length' => ['SELECT LENGTH(name)', 'LENGTH(name)', Length::class],
            'lower' => ['SELECT LOWER(name)', 'LOWER(name)', Lower::class],
            'random string' => ['SELECT RANDOM_STRING(12)', 'RANDOM_STRING(12)', RandomString::class],
            'replace' => ['SELECT REPLACE(name, "a", "b")', 'REPLACE(name, "a", "b")', Replace::class],
            'reverse' => ['SELECT REVERSE(name)', 'REVERSE(name)', Reverse::class],
            'upper' => ['SELECT UPPER(name)', 'UPPER(name)', Upper::class],
            'coalesce' => ['SELECT COALESCE(a, b, c)', 'COALESCE(a, b, c)', Coalesce::class],
            'coalesce not empty' => ['SELECT COALESCE_NE(a, b, c)', 'COALESCE_NE(a, b, c)', CoalesceNotEmpty::class],
            'random bytes' => ['SELECT RANDOM_BYTES(8)', 'RANDOM_BYTES(8)', RandomBytes::class],
            'lpad' => ['SELECT LPAD(name, 4, "0")', 'LPAD(name, 4, "0")', LeftPad::class],
            'rpad' => ['SELECT RPAD(name, 4, "0")', 'RPAD(name, 4, "0")', RightPad::class],
            'array combine' => ['SELECT ARRAY_COMBINE(keys, values)', 'ARRAY_COMBINE(keys, values)', ArrayCombine::class],
            'array merge' => ['SELECT ARRAY_MERGE(first, second)', 'ARRAY_MERGE(first, second)', ArrayMerge::class],
            'array filter' => ['SELECT ARRAY_FILTER(items)', 'ARRAY_FILTER(items)', ArrayFilter::class],
            'col split' => ['SELECT COL_SPLIT(items, "item_%index", "id")', 'COL_SPLIT(items, "item_%index", "id")', ColSplit::class],
            'current date' => ['SELECT CURDATE(true)', 'CURDATE(true)', CurrentDate::class],
            'current time' => ['SELECT CURTIME(true)', 'CURTIME(true)', CurrentTime::class],
            'current timestamp' => ['SELECT CURRENT_TIMESTAMP()', 'CURRENT_TIMESTAMP()', CurrentTimestamp::class],
            'now' => ['SELECT NOW(true)', 'NOW(true)', Now::class],
            'date format' => ['SELECT DATE_FORMAT(dateField, "Y-m-d")', 'DATE_FORMAT(dateField, "Y-m-d")', DateFormat::class],
            'date diff' => ['SELECT DATE_DIFF(startDate, endDate)', 'DATE_DIFF(startDate, endDate)', DateDiff::class],
            'match against' => [
                'SELECT MATCH(name) AGAINST("term" IN NATURAL MODE)',
                'MATCH(name) AGAINST("term" IN NATURAL MODE)',
                Fulltext::class,
            ],
            'if' => ['SELECT IF(a > 1, "yes", "no")', 'IF(a > 1, yes, no)', SelectIf::class],
            'ifnull' => ['SELECT IFNULL(name, "unknown")', 'IFNULL(name IS NULL, unknown)', SelectIfNull::class],
            'isnull' => ['SELECT ISNULL(name)', 'ISNULL(name)', SelectIsNull::class],
            'substring' => ['SELECT SUBSTRING(name, 1, 2)', 'SUBSTRING(name, 1, 2)', Substring::class],
            'substr' => ['SELECT SUBSTR(name, 1, 2)', 'SUBSTRING(name, 1, 2)', Substring::class],
            'locate' => ['SELECT LOCATE("foo", name, 2)', 'LOCATE(foo, name, 2)', Locate::class],
        ];
    }
}
