<?php

namespace SQL\Builder;

use FQL\Exception;
use FQL\Query\TestProvider;
use FQL\Sql\Provider as SqlProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises every function branch in QueryBuildingVisitor::applyFunctionCall(). The
 * assertions are lightweight (field label presence on `getSelectedFields()`) so the
 * test is fast; the goal is branch coverage rather than computational correctness,
 * which is covered by the individual Functions/* test suites.
 */
class FunctionInvocationTest extends TestCase
{
    /**
     * @dataProvider functionCases
     */
    public function testFunctionBuildsSelectedField(string $sql, string $expectedKey): void
    {
        $query = SqlProvider::compile($sql)->applyTo(new TestProvider());
        $fields = $query->getSelectedFields();
        $this->assertArrayHasKey($expectedKey, $fields);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function functionCases(): array
    {
        return [
            // Aggregates with and without DISTINCT
            'avg' => ['SELECT AVG(price)', 'AVG(price)'],
            'sum distinct' => ['SELECT SUM(DISTINCT price)', 'SUM(DISTINCT price)'],
            'max' => ['SELECT MAX(price)', 'MAX(price)'],
            'min distinct' => ['SELECT MIN(DISTINCT price)', 'MIN(DISTINCT price)'],
            'count' => ['SELECT COUNT(id)', 'COUNT(id)'],
            'count distinct' => ['SELECT COUNT(DISTINCT id)', 'COUNT(DISTINCT id)'],
            'group concat' => ['SELECT GROUP_CONCAT(name, "|")', 'GROUP_CONCAT(name, "|")'],

            // Hashing
            'md5' => ['SELECT MD5(token)', 'MD5(token)'],
            'sha1' => ['SELECT SHA1(token)', 'SHA1(token)'],

            // Math
            'add' => ['SELECT ADD(a, b, c)', 'ADD(a, b, c)'],
            'sub' => ['SELECT SUB(a, b)', 'SUB(a, b)'],
            'multiply' => ['SELECT MULTIPLY(a, b)', 'MULTIPLY(a, b)'],
            'divide' => ['SELECT DIVIDE(a, b)', 'DIVIDE(a, b)'],
            'ceil' => ['SELECT CEIL(price)', 'CEIL(price)'],
            'floor' => ['SELECT FLOOR(price)', 'FLOOR(price)'],
            'mod with int literal' => ['SELECT MOD(n, 7)', 'MOD(n, 7)'],
            'round no precision' => ['SELECT ROUND(price, 0)', 'ROUND(price, 0)'],
            'round with precision' => ['SELECT ROUND(price, 3)', 'ROUND(price, 3)'],

            // String
            'base64 encode' => ['SELECT BASE64_ENCODE(name)', 'BASE64_ENCODE(name)'],
            'base64 decode' => ['SELECT BASE64_DECODE(name)', 'BASE64_DECODE(name)'],
            'concat' => ['SELECT CONCAT(a, b)', 'CONCAT(a, b)'],
            'concat ws' => ['SELECT CONCAT_WS("-", a, b)', 'CONCAT_WS("-", a, b)'],
            'lower' => ['SELECT LOWER(name)', 'LOWER(name)'],
            'upper' => ['SELECT UPPER(name)', 'UPPER(name)'],
            'length' => ['SELECT LENGTH(name)', 'LENGTH(name)'],
            'reverse' => ['SELECT REVERSE(name)', 'REVERSE(name)'],
            'explode' => ['SELECT EXPLODE(csv, ",")', 'EXPLODE(csv, ",")'],
            'implode' => ['SELECT IMPLODE(list, ",")', 'IMPLODE(list, ",")'],
            'random string default' => ['SELECT RANDOM_STRING(10)', 'RANDOM_STRING(10)'],
            'replace' => ['SELECT REPLACE(name, "a", "b")', 'REPLACE(name, "a", "b")'],
            'lpad' => ['SELECT LPAD(name, 8, "0")', 'LPAD(name, 8, "0")'],
            'rpad' => ['SELECT RPAD(name, 8, "0")', 'RPAD(name, 8, "0")'],
            'substring with length' => ['SELECT SUBSTRING(name, 1, 5)', 'SUBSTRING(name, 1, 5)'],
            'substring without length' => ['SELECT SUBSTRING(name, 1)', 'SUBSTRING(name, 1)'],
            'substr alias' => ['SELECT SUBSTR(name, 1, 2)', 'SUBSTR(name, 1, 2)'],
            'locate with position' => ['SELECT LOCATE("x", name, 2)', 'LOCATE("x", name, 2)'],
            'locate no position' => ['SELECT LOCATE("x", name)', 'LOCATE("x", name)'],

            // Utils
            'coalesce' => ['SELECT COALESCE(a, b, c)', 'COALESCE(a, b, c)'],
            'coalesce not empty' => ['SELECT COALESCE_NE(a, b, c)', 'COALESCE_NE(a, b, c)'],
            'uuid' => ['SELECT UUID()', 'UUID()'],
            'random bytes' => ['SELECT RANDOM_BYTES(16)', 'RANDOM_BYTES(16)'],
            'array combine' => ['SELECT ARRAY_COMBINE(keys, values)', 'ARRAY_COMBINE(keys, values)'],
            'array merge' => ['SELECT ARRAY_MERGE(a, b)', 'ARRAY_MERGE(a, b)'],
            'array filter' => ['SELECT ARRAY_FILTER(items)', 'ARRAY_FILTER(items)'],
            'array search' => ['SELECT ARRAY_SEARCH(items, "x")', 'ARRAY_SEARCH(items, "x")'],
            'col split two args' => ['SELECT COL_SPLIT(items, "fmt")', 'COL_SPLIT(items, "fmt")'],
            'col split three args' => ['SELECT COL_SPLIT(items, "fmt", "keyField")', 'COL_SPLIT(items, "fmt", "keyField")'],
            'col split single arg' => ['SELECT COL_SPLIT(items)', 'COL_SPLIT(items)'],
            'cast' => ['SELECT CAST(price AS INT)', 'CAST(price AS INT)'],

            // Date/Time functions (numeric bool argument)
            'curdate true' => ['SELECT CURDATE(true)', 'CURDATE(TRUE)'],
            'curtime true' => ['SELECT CURTIME(true)', 'CURTIME(TRUE)'],
            'current timestamp' => ['SELECT CURRENT_TIMESTAMP()', 'CURRENT_TIMESTAMP()'],
            'now with bool true' => ['SELECT NOW(true)', 'NOW(TRUE)'],
            'date format' => ['SELECT DATE_FORMAT(dateField, "Y-m-d")', 'DATE_FORMAT(dateField, "Y-m-d")'],
            'from unixtime' => ['SELECT FROM_UNIXTIME(dateField, "Y-m-d")', 'FROM_UNIXTIME(dateField, "Y-m-d")'],
            'str to date' => ['SELECT STR_TO_DATE(dateField, "%Y-%m-%d")', 'STR_TO_DATE(dateField, "%Y-%m-%d")'],
            'date diff' => ['SELECT DATE_DIFF(a, b)', 'DATE_DIFF(a, b)'],
            'date add' => ['SELECT DATE_ADD(a, "+1 day")', 'DATE_ADD(a, "+1 day")'],
            'date sub' => ['SELECT DATE_SUB(a, "-1 day")', 'DATE_SUB(a, "-1 day")'],
            'year' => ['SELECT YEAR(dateField)', 'YEAR(dateField)'],
            'month' => ['SELECT MONTH(dateField)', 'MONTH(dateField)'],
            'day' => ['SELECT DAY(dateField)', 'DAY(dateField)'],

            // Conditional
            'if' => ['SELECT IF(a > 1, "yes", "no")', 'IF(a > 1, "yes", "no")'],
            'ifnull' => ['SELECT IFNULL(name, "unknown")', 'IFNULL(name, "unknown")'],
            'isnull' => ['SELECT ISNULL(name)', 'ISNULL(name)'],

            // Fulltext
            'match against split' => [
                'SELECT MATCH(name) AGAINST("term" IN NATURAL MODE)',
                'MATCH(name) AGAINST("term IN NATURAL MODE")',
            ],
        ];
    }

    public function testUnknownFunctionThrowsAtExecutionTime(): void
    {
        // The parser accepts any function name — resolution happens at runtime when
        // the evaluator dispatches through FunctionInvoker. Unknown names throw there.
        $this->expectException(Exception\UnexpectedValueException::class);
        $path = (string) realpath(__DIR__ . '/../../../examples/data/products.json');
        $sql = sprintf('SELECT NONSENSE(name) FROM json(%s).data.products', $path);
        iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());
    }
}
