<?php

namespace SQL;

use FQL\Exception\QueryLogicException;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\Node\FromClauseNode;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Provider as SqlProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Subqueries are supported in JOIN only. A subquery in FROM is a parse error
 * pointing at the opening parenthesis and naming the WITH alternative.
 */
class SqlFromSubqueryTest extends TestCase
{
    private string $usersJson;

    protected function setUp(): void
    {
        $this->usersJson = (string) realpath(__DIR__ . '/../../examples/data/users.json');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fromSubqueries(): array
    {
        return [
            'selected field' => ['SELECT id FROM (SELECT id, name FROM json(%s).data.users) AS s'],
            'alias path' => ['SELECT s.id FROM (SELECT id, name FROM json(%s).data.users) AS s'],
            'star' => ['SELECT * FROM (SELECT id FROM json(%s).data.users) AS s LIMIT 2'],
            'without alias' => ['SELECT * FROM (SELECT id FROM json(%s).data.users)'],
            'in a CTE body' => ['WITH t AS (SELECT * FROM (SELECT id FROM json(%s).data.users) AS s) SELECT * FROM t'],
            'in a union branch' => [
                'SELECT id FROM json(%1$s).data.users UNION SELECT id FROM (SELECT id FROM json(%1$s).data.users) AS s',
            ],
            'describe' => ['DESCRIBE (SELECT id FROM json(%s).data.users)'],
        ];
    }

    #[DataProvider('fromSubqueries')]
    public function testSubqueryInFromIsAParseError(string $sql): void
    {
        $sql = sprintf($sql, $this->usersJson);
        try {
            SqlProvider::compile($sql)->toQuery();
            $this->fail('ParseException expected');
        } catch (ParseException $e) {
            $this->assertStringStartsWith(
                'Subqueries in FROM are not supported; use WITH name AS (...) SELECT ... FROM name at line 1',
                $e->getMessage()
            );
            $this->assertSame(
                'Subqueries in FROM are not supported; use WITH name AS (...) SELECT ... FROM name',
                $e->getReason()
            );
            $this->assertSame('syntax-error', $e->getRule());
            $this->assertSame('unsupported-syntax', $e->getParams()['kind']);
            $this->assertSame('FROM source', $e->getParams()['context']);

            $span = $e->getSpan();
            $this->assertNotNull($span);
            $this->assertSame('(', substr($sql, $span->start, $span->end - $span->start));
            $this->assertSame(1, preg_match('/(?:FROM|DESCRIBE) (\()/', $sql, $m, PREG_OFFSET_CAPTURE));
            $this->assertSame($m[1][1], $span->start);
        }
    }

    public function testLintReportsSubqueryInFrom(): void
    {
        $report = SqlProvider::lint('SELECT id FROM (SELECT id FROM json(a.json)) AS s');
        $issues = $report->issues;
        $this->assertCount(1, $issues);
        $this->assertSame('syntax-error', $issues[0]->rule);
        $this->assertSame('unsupported-syntax', $issues[0]->params['kind']);
        $this->assertSame(15, $issues[0]->span?->start);
    }

    public function testSubqueryInJoinStillWorks(): void
    {
        $sql = sprintf(
            'SELECT id, s.name AS sname FROM json(%1$s).data.users'
            . ' INNER JOIN (SELECT id, name FROM json(%1$s).data.users WHERE id > 1) AS s ON id = s.id',
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertGreaterThan(1, $row['id']);
            $this->assertIsString($row['sname']);
        }
    }

    public function testWithIsTheSupportedAlternative(): void
    {
        $sql = sprintf(
            'WITH s AS (SELECT id, name FROM json(%s).data.users ORDER BY id DESC) SELECT id FROM s LIMIT 2',
            $this->usersJson
        );
        $rows = iterator_to_array(SqlProvider::compile($sql)->toQuery()->execute()->fetchAll());

        $this->assertCount(2, $rows);
        $this->assertSame(['id'], array_keys($rows[0]));
        $this->assertGreaterThan($rows[1]['id'], $rows[0]['id']);
    }

    public function testBuilderRejectsSubqueryInFromOfAnEditedAst(): void
    {
        $statement = SqlProvider::compile(sprintf('SELECT id FROM json(%s).data.users', $this->usersJson))
            ->toAst();
        $inner = SqlProvider::compile(sprintf('SELECT id, name FROM json(%s).data.users', $this->usersJson))
            ->toAst();
        $from = $statement->from;
        $this->assertNotNull($from);
        $edited = $statement->withFrom(
            new FromClauseNode(new SubQueryNode($inner, $from->position), 's', $from->position)
        );

        $this->expectException(QueryLogicException::class);
        $this->expectExceptionMessage('Subqueries in FROM are not supported');
        (new \FQL\Sql\Builder\QueryBuilder())->build($edited);
    }
}
