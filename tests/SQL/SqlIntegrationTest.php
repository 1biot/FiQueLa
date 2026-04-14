<?php

namespace SQL;

use FQL\Sql\Sql;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

class SqlIntegrationTest extends TestCase
{
    private Json $json;

    protected function setUp(): void
    {
        $jsonFile = realpath(__DIR__ . '/../../examples/data/products.json');
        $this->json = Json::open($jsonFile);
    }

    public function testParseWithQueryWhereOrderLimitOffset(): void
    {
        $sql = 'SELECT id, name, price FROM data.products WHERE price >= 200 ORDER BY price DESC LIMIT 2 1';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([5, 3], array_column($rows, 'id'));
    }

    public function testParseWithQueryGroupByHaving(): void
    {
        $sql = 'SELECT brand.name AS brand, COUNT(id) AS total FROM data.products GROUP BY brand.name HAVING total > 1 ORDER BY total DESC';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([
            ['brand' => 'Brand B', 'total' => 2],
        ], $rows);
    }

    public function testParseWithQueryCaseAndExclude(): void
    {
        $sql = 'SELECT name, CASE WHEN price > 300 THEN "high" ELSE "low" END AS tier, EXCLUDE description FROM data.products WHERE price >= 300 ORDER BY price ASC';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame('low', $rows[0]['tier']);
        $this->assertArrayNotHasKey('description', $rows[0]);
    }

    public function testParseWithQueryNestedConditions(): void
    {
        $sql = 'SELECT id, price FROM data.products WHERE price > 100 AND (brand.code = "BRAND-B" OR brand.code = "BRAND-D") ORDER BY id ASC';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([3, 4, 5], array_column($rows, 'id'));
    }

    public function testParseWithQueryRegexpCondition(): void
    {
        $sql = 'SELECT id FROM data.products WHERE name REGEXP "^Product [A-B]$" ORDER BY id ASC';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([1, 2], array_column($rows, 'id'));
    }

    public function testParseWithQueryNotRegexpCondition(): void
    {
        $sql = 'SELECT id FROM data.products WHERE name NOT REGEXP "^Product [A-B]$" ORDER BY id ASC';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([3, 4, 5], array_column($rows, 'id'));
    }

    public function testParseWithQuerySelectAllAndExclude(): void
    {
        $sql = 'SELECT *, EXCLUDE description FROM data.products WHERE id = 1';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame(1, $rows[0]['id']);
        $this->assertArrayNotHasKey('description', $rows[0]);
    }

    public function testParseWithQuerySelectAllAndExcludeMultipleFields(): void
    {
        $sql = 'SELECT *, EXCLUDE description, price FROM data.products WHERE id = 1';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame(1, $rows[0]['id']);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayNotHasKey('description', $rows[0]);
        $this->assertArrayNotHasKey('price', $rows[0]);
    }

    public function testParseWithQueryOffsetClause(): void
    {
        $sql = 'SELECT id FROM data.products ORDER BY id ASC OFFSET 2';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertSame([3, 4, 5], array_column($rows, 'id'));
    }

    public function testParseWithFileQueryJoin(): void
    {
        $usersPath = realpath(__DIR__ . '/../../examples/data/users.json');
        $ordersPath = realpath(__DIR__ . '/../../examples/data/orders.xml');

        $sql = sprintf(
            'SELECT * FROM json(%s).data.users INNER JOIN xml(%s).orders.order AS o ON id = user_id WHERE o.total_price > 200',
            $usersPath,
            $ordersPath
        );

        $results = (new Sql($sql))->parse();
        $rows = iterator_to_array($results->fetchAll());

        $this->assertNotEmpty($rows);
    }

    public function testParseWithFileQueryLeftJoin(): void
    {
        $usersPath = realpath(__DIR__ . '/../../examples/data/users.json');
        $ordersPath = realpath(__DIR__ . '/../../examples/data/orders.xml');

        $sql = sprintf(
            'SELECT * FROM json(%s).data.users LEFT JOIN xml(%s).orders.order AS o ON id = user_id',
            $usersPath,
            $ordersPath
        );

        $results = (new Sql($sql))->parse();
        $rows = iterator_to_array($results->fetchAll());

        $this->assertNotEmpty($rows);
    }

    public function testParseWithFileQueryRightJoin(): void
    {
        $usersPath = realpath(__DIR__ . '/../../examples/data/users.json');
        $ordersPath = realpath(__DIR__ . '/../../examples/data/orders.xml');

        $sql = sprintf(
            'SELECT * FROM json(%s).data.users RIGHT JOIN xml(%s).orders.order AS o ON id = user_id',
            $usersPath,
            $ordersPath
        );

        $results = (new Sql($sql))->parse();
        $rows = iterator_to_array($results->fetchAll());

        $this->assertNotEmpty($rows);
    }

    public function testParseWithFileQueryFullJoin(): void
    {
        $usersPath = realpath(__DIR__ . '/../../examples/data/users.json');
        $ordersPath = realpath(__DIR__ . '/../../examples/data/orders.xml');

        $sql = sprintf(
            'SELECT * FROM json(%s).data.users FULL JOIN xml(%s).orders.order AS o ON id = user_id',
            $usersPath,
            $ordersPath
        );

        $results = (new Sql($sql))->parse();
        $rows = iterator_to_array($results->fetchAll());

        $this->assertNotEmpty($rows);
    }

    public function testMissingCommaInSelectThrows(): void
    {
        $sql = 'SELECT id name FROM data.products';
        $parser = new Sql($sql);

        $this->expectException(\FQL\Exception\UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected comma between SELECT expressions');

        $parser->parseWithQuery($this->json->query());
    }

    public function testMissingCommaInGroupByThrows(): void
    {
        $sql = 'SELECT brand.name, COUNT(id) AS total FROM data.products GROUP BY brand.name brand.code';
        $parser = new Sql($sql);

        $this->expectException(\FQL\Exception\UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected comma between GROUP BY fields');

        $parser->parseWithQuery($this->json->query());
    }

    public function testCommasInSelectAreRequired(): void
    {
        $sql = 'SELECT id, name, price FROM data.products LIMIT 2';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('price', $rows[0]);
    }

    public function testCommasWithFunctionsAndAliases(): void
    {
        $sql = 'SELECT id, ROUND(price, 2) AS rounded, name FROM data.products LIMIT 1';
        $parser = new Sql($sql);
        $query = $parser->parseWithQuery($this->json->query());

        $rows = iterator_to_array($query->execute()->fetchAll());

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('rounded', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
    }
}
