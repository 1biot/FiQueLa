<?php

namespace SQL;

use FQL\Sql\Provider as SqlProvider;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenType;
use PHPUnit\Framework\TestCase;

class SqlSubqueryJoinTest extends TestCase
{
    private string $usersJson;
    private string $ordersXml;

    protected function setUp(): void
    {
        $this->usersJson = realpath(__DIR__ . '/../../examples/data/users.json');
        $this->ordersXml = realpath(__DIR__ . '/../../examples/data/orders.xml');
    }

    public function testTokenizerEmitsParenBoundaryForSubqueryJoin(): void
    {
        $sql = sprintf(
            'SELECT id FROM json(%s).data.users LEFT JOIN (SELECT id, name FROM json(%s).data.users WHERE id > 1) AS u ON id = u.id',
            $this->usersJson,
            $this->usersJson
        );
        $tokens = array_values(array_filter(
            (new Tokenizer())->tokenize($sql),
            static fn ($t) => !$t->type->isTrivia()
        ));
        $types = array_map(static fn ($t) => $t->type, $tokens);

        $openIdx = array_search(TokenType::PAREN_OPEN, $types, true);
        $closeIdx = array_search(TokenType::PAREN_CLOSE, $types, true);
        $this->assertNotFalse($openIdx);
        $this->assertNotFalse($closeIdx);
        $this->assertGreaterThan($openIdx, $closeIdx);

        $outerSelect = array_search(TokenType::KEYWORD_SELECT, $types, true);
        $outerLeft = array_search(TokenType::KEYWORD_LEFT, $types, true);
        $outerJoin = array_search(TokenType::KEYWORD_JOIN, $types, true);
        $this->assertLessThan($outerLeft, $outerSelect);
        $this->assertLessThan($outerJoin, $outerLeft);
        $this->assertLessThan($openIdx, $outerJoin);

        // Inner SELECT appears between ( and )
        $innerSelect = null;
        for ($i = $openIdx + 1; $i < $closeIdx; $i++) {
            if ($types[$i] === TokenType::KEYWORD_SELECT) {
                $innerSelect = $i;
                break;
            }
        }
        $this->assertNotNull($innerSelect, 'Inner SELECT token must appear between ( and )');
    }

    public function testSubqueryJoinParsesAndExecutes(): void
    {
        $sql = sprintf(
            'SELECT id, name, o.id AS orderId FROM json(%s).data.users '
            . 'LEFT JOIN (SELECT id, user_id FROM xml(%s).orders.order WHERE total_price > 100) AS o '
            . 'ON id = user_id',
            $this->usersJson,
            $this->ordersXml
        );

        $parser = SqlProvider::compile($sql);
        $query = $parser->toQuery();
        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('orderId', $rows[0]);
    }

    public function testSubqueryJoinFiltersData(): void
    {
        // Subquery filters orders to only total_price > 500
        $sql = sprintf(
            'SELECT id, name, o.id AS orderId, o.total_price AS totalPrice '
            . 'FROM json(%s).data.users '
            . 'LEFT JOIN (SELECT id, user_id, total_price FROM xml(%s).orders.order WHERE total_price > 500) AS o '
            . 'ON id = user_id '
            . 'ORDER BY totalPrice DESC',
            $this->usersJson,
            $this->ordersXml
        );

        $parser = SqlProvider::compile($sql);
        $rows = iterator_to_array($parser->toQuery()->execute()->fetchAll());

        // All matched orders should have totalPrice > 500
        foreach ($rows as $row) {
            if ($row['totalPrice'] !== null) {
                $this->assertGreaterThan(500, $row['totalPrice']);
            }
        }
    }

    public function testSubqueryJoinToStringRendersSubquery(): void
    {
        $sql = sprintf(
            'SELECT id FROM json(%s).data.users '
            . 'LEFT JOIN (SELECT id, user_id FROM xml(%s).orders.order WHERE total_price > 100) AS o '
            . 'ON id = user_id',
            $this->usersJson,
            $this->ordersXml
        );

        $parser = SqlProvider::compile($sql);
        $query = $parser->toQuery();
        $queryString = (string) $query;

        // Should contain subquery in parens, not direct reference
        $this->assertStringContainsString('(', $queryString);
        $this->assertStringContainsString(')', $queryString);
        $this->assertStringContainsString('WHERE', $queryString);
        $this->assertStringContainsString('AS o ON', $queryString);
    }

    public function testSimpleJoinToStringRendersDirectReference(): void
    {
        $sql = sprintf(
            'SELECT id FROM json(%s).data.users '
            . 'LEFT JOIN xml(%s).orders.order AS o ON id = user_id',
            $this->usersJson,
            $this->ordersXml
        );

        $parser = SqlProvider::compile($sql);
        $query = $parser->toQuery();
        $queryString = (string) $query;

        // Should render as direct reference, not wrapped in ( SELECT ... )
        $this->assertStringContainsString('xml(orders.xml).orders.order AS o', $queryString);
        $this->assertStringNotContainsString('(' . PHP_EOL, $queryString);
    }

    public function testSubqueryWithSelectFields(): void
    {
        $sql = sprintf(
            'SELECT name, o.total_price AS price FROM json(%s).data.users '
            . 'LEFT JOIN (SELECT user_id, total_price FROM xml(%s).orders.order) AS o '
            . 'ON id = user_id LIMIT 3',
            $this->usersJson,
            $this->ordersXml
        );

        $parser = SqlProvider::compile($sql);
        $rows = iterator_to_array($parser->toQuery()->execute()->fetchAll());

        $this->assertCount(3, $rows);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('price', $rows[0]);
    }

    public function testLimitBeforeUnionStillWorks(): void
    {
        $sql = sprintf(
            'SELECT id, name FROM json(%s).data.users LIMIT 2 '
            . 'UNION ALL '
            . 'SELECT id, name FROM json(%s).data.users LIMIT 2',
            $this->usersJson,
            $this->usersJson
        );

        $parser = SqlProvider::compile($sql);
        $rows = iterator_to_array($parser->toQuery()->execute()->fetchAll());

        $this->assertCount(4, $rows);
    }
}
