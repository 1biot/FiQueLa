<?php

namespace Traits;

use FQL\Enum;
use FQL\Query\TestProvider;
use FQL\Sql\Ast\Expression\FunctionCallNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Data-provider sweep of the fluent `Traits\Select` helpers. Each helper is a
 * thin wrapper that parses its argument(s) into AST nodes and stores a
 * `FunctionCallNode` (or an aggregate triple) under the SELECT slot. These
 * tests exercise every helper once to make sure:
 *  - the call is fluent (returns the Query instance)
 *  - the right SQL function name ends up in the stored AST
 *  - the selected-fields map carries exactly one entry per call
 *
 * Functional behaviour (value semantics, runtime evaluation) is covered by
 * the corresponding `tests/Functions/*` and integration tests; this suite is
 * purely about the trait surface.
 */
class SelectFluentHelpersTest extends TestCase
{
    /** @return iterable<string, array{string, array<int, mixed>, string}> */
    public static function scalarHelperProvider(): iterable
    {
        yield 'lower'               => ['lower',               ['name'],                      'LOWER'];
        yield 'upper'               => ['upper',               ['name'],                      'UPPER'];
        yield 'reverse'             => ['reverse',             ['name'],                      'REVERSE'];
        yield 'length'              => ['length',              ['name'],                      'LENGTH'];
        yield 'md5'                 => ['md5',                 ['name'],                      'MD5'];
        yield 'sha1'                => ['sha1',                ['name'],                      'SHA1'];
        yield 'round/default'       => ['round',               ['price'],                     'ROUND'];
        yield 'round/custom'        => ['round',               ['price', 2],                  'ROUND'];
        yield 'ceil'                => ['ceil',                ['price'],                     'CEIL'];
        yield 'floor'               => ['floor',               ['price'],                     'FLOOR'];
        yield 'modulo'              => ['modulo',              ['price', 5],                  'MOD'];
        yield 'add'                 => ['add',                 ['a', 'b'],                    'ADD'];
        yield 'subtract'            => ['subtract',            ['a', 'b'],                    'SUB'];
        yield 'multiply'            => ['multiply',            ['a', 'b'],                    'MULTIPLY'];
        yield 'divide'              => ['divide',              ['a', 'b'],                    'DIVIDE'];
        yield 'concat'              => ['concat',              ['a', 'b', 'c'],               'CONCAT'];
        yield 'concatWithSeparator' => ['concatWithSeparator', [' | ', 'a', 'b'],             'CONCAT_WS'];
        yield 'coalesce'            => ['coalesce',            ['a', 'b', 'c'],               'COALESCE'];
        yield 'coalesceNotEmpty'    => ['coalesceNotEmpty',    ['a', 'b', 'c'],               'COALESCE_NE'];
        yield 'explode'             => ['explode',             ['tags', ','],                 'EXPLODE'];
        yield 'split'               => ['split',               ['tags', '|'],                 'EXPLODE'];
        yield 'implode'             => ['implode',             ['tags', ','],                 'IMPLODE'];
        yield 'glue'                => ['glue',                ['tags', ','],                 'IMPLODE'];
        yield 'randomString'        => ['randomString',        [8],                           'RANDOM_STRING'];
        yield 'randomBytes'         => ['randomBytes',         [16],                          'RANDOM_BYTES'];
        yield 'uuid'                => ['uuid',                [],                            'UUID'];
        yield 'fromBase64'          => ['fromBase64',          ['data'],                      'BASE64_DECODE'];
        yield 'toBase64'            => ['toBase64',            ['data'],                      'BASE64_ENCODE'];
        yield 'leftPad'             => ['leftPad',             ['code', 10, '0'],             'LPAD'];
        yield 'rightPad'            => ['rightPad',            ['code', 10, '0'],             'RPAD'];
        yield 'replace'             => ['replace',             ['text', 'old', 'new'],        'REPLACE'];
        yield 'arrayCombine'        => ['arrayCombine',        ['keys', 'values'],            'ARRAY_COMBINE'];
        yield 'arrayMerge'          => ['arrayMerge',          ['a', 'b'],                    'ARRAY_MERGE'];
        yield 'arrayFilter'         => ['arrayFilter',         ['items'],                     'ARRAY_FILTER'];
        yield 'arraySearch'         => ['arraySearch',         ['items', 'foo'],              'ARRAY_SEARCH'];
        yield 'colSplit'            => ['colSplit',            ['items', 'json', 'id'],       'COL_SPLIT'];
        yield 'strToDate'           => ['strToDate',           ['date', 'Y-m-d'],             'STR_TO_DATE'];
        yield 'formatDate'          => ['formatDate',          ['date'],                      'DATE_FORMAT'];
        yield 'fromUnixTime'        => ['fromUnixTime',        ['epoch'],                     'FROM_UNIXTIME'];
        yield 'currentDate'         => ['currentDate',         [],                            'CURDATE'];
        yield 'currentTime'         => ['currentTime',         [],                            'CURTIME'];
        yield 'currentTimestamp'    => ['currentTimestamp',    [],                            'CURRENT_TIMESTAMP'];
        yield 'now'                 => ['now',                 [],                            'NOW'];
        yield 'dateDiff'            => ['dateDiff',            ['startDate', 'endDate'],      'DATE_DIFF'];
        yield 'dateAdd'             => ['dateAdd',             ['date', '+1 day'],            'DATE_ADD'];
        yield 'dateSub'             => ['dateSub',             ['date', '-1 week'],           'DATE_SUB'];
        yield 'year'                => ['year',                ['date'],                      'YEAR'];
        yield 'month'               => ['month',               ['date'],                      'MONTH'];
        yield 'day'                 => ['day',                 ['date'],                      'DAY'];
        yield 'ifNull'              => ['ifNull',              ['x', '0'],                    'IFNULL'];
        yield 'isNull'              => ['isNull',              ['x'],                         'ISNULL'];
        yield 'substring/2arg'      => ['substring',           ['name', 0, 5],                'SUBSTRING'];
        yield 'substring/omit-len'  => ['substring',           ['name', 2],                   'SUBSTRING'];
        yield 'locate/2arg'         => ['locate',              ['@', 'email'],                'LOCATE'];
        yield 'locate/3arg'         => ['locate',              ['@', 'email', 1],             'LOCATE'];
    }

    /** @return iterable<string, array{string, array<int, mixed>}> */
    public static function aggregateHelperProvider(): iterable
    {
        yield 'count'                   => ['count',       []];
        yield 'count/field'             => ['count',       ['id']];
        yield 'count/distinct'          => ['count',       ['id', true]];
        yield 'sum'                     => ['sum',         ['price']];
        yield 'sum/distinct'            => ['sum',         ['price', true]];
        yield 'avg'                     => ['avg',         ['price']];
        yield 'min'                     => ['min',         ['price']];
        yield 'min/distinct'            => ['min',         ['price', true]];
        yield 'max'                     => ['max',         ['price']];
        yield 'max/distinct'            => ['max',         ['price', true]];
        yield 'groupConcat'             => ['groupConcat', ['id']];
        yield 'groupConcat/separator'   => ['groupConcat', ['id', '|']];
        yield 'groupConcat/distinct'    => ['groupConcat', ['id', ',', true]];
    }

    #[DataProvider('scalarHelperProvider')]
    public function testScalarHelperStoresFunctionCallNode(string $method, array $args, string $expectedName): void
    {
        $query = new TestProvider();
        /** @var TestProvider $result */
        $result = $query->{$method}(...$args);
        $this->assertSame($query, $result, "Fluent helper $method must return \$this");

        $selected = $query->getSelectedFields();
        $this->assertCount(1, $selected, "Helper $method should add exactly one SELECT field");

        $entry = reset($selected);
        $this->assertInstanceOf(FunctionCallNode::class, $entry['expression']);
        $this->assertSame($expectedName, $entry['expression']->name);
        $this->assertNull($entry['aggregate'], "Helper $method must not populate the aggregate slot");
    }

    #[DataProvider('aggregateHelperProvider')]
    public function testAggregateHelperStoresAggregateMeta(string $method, array $args): void
    {
        $query = new TestProvider();
        $result = $query->{$method}(...$args);
        $this->assertSame($query, $result);

        $selected = $query->getSelectedFields();
        $this->assertCount(1, $selected);

        $entry = reset($selected);
        $this->assertNull($entry['expression'], "Aggregate helper $method must leave expression slot null");
        $this->assertIsArray($entry['aggregate']);
        // Aggregate slot is `{class, expression, options}` — `class` resolves to
        // the registered `Functions\Aggregate\*` implementation.
        $this->assertArrayHasKey('class', $entry['aggregate']);
        $this->assertArrayHasKey('expression', $entry['aggregate']);
        $this->assertArrayHasKey('options', $entry['aggregate']);
        $this->assertTrue(class_exists($entry['aggregate']['class']));
    }

    public function testFulltextFluentHelperEmitsMatchAgainstNode(): void
    {
        $query = new TestProvider();
        $query->fulltext(['title', 'body'], 'search term');
        $selected = $query->getSelectedFields();
        $this->assertCount(1, $selected);
        // Fulltext uses MatchAgainstNode rather than a FunctionCall.
        $entry = reset($selected);
        $this->assertNotNull($entry['expression']);
        $this->assertStringContainsString('MatchAgainst', (string) $entry['expression']::class);
    }

    public function testMatchAgainstFluentHelperReusesFulltext(): void
    {
        $query = new TestProvider();
        $query->matchAgainst(['title'], 'term', Enum\Fulltext::NATURAL);
        $this->assertCount(1, $query->getSelectedFields());
    }

    public function testCaseBuilderFluentChain(): void
    {
        $query = new TestProvider();
        $query->case()
            ->whenCase('price > 100', '"expensive"')
            ->whenCase('price > 50', '"medium"')
            ->elseCase('"cheap"')
            ->endCase();

        $selected = $query->getSelectedFields();
        $this->assertCount(1, $selected);
        $entry = reset($selected);
        $this->assertStringContainsString('Case', (string) $entry['expression']::class);
    }

    public function testIfFluentHelper(): void
    {
        $query = new TestProvider();
        // `if()` parses its arguments as expressions — pass function-call-style
        // args so parseExpression doesn't choke on bare comparison operators.
        $query->if('ISNULL(price)', '"unknown"', '"known"');
        $selected = $query->getSelectedFields();
        $this->assertCount(1, $selected);
        $entry = reset($selected);
        $this->assertInstanceOf(FunctionCallNode::class, $entry['expression']);
        $this->assertSame('IF', $entry['expression']->name);
    }

    public function testDistinctToggle(): void
    {
        $query = new TestProvider();
        $result = $query->select('*')->distinct();
        $this->assertSame($query, $result);
        // `distinct(false)` toggles the flag off — fluent return preserved.
        $this->assertSame($query, $query->distinct(false));
    }

    public function testExcludeStoresExcludedFields(): void
    {
        $query = new TestProvider();
        $query->exclude('password', 'secret');
        $this->assertSame(['password', 'secret'], $query->getExcludedFields());
    }
}
