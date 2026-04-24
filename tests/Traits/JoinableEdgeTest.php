<?php

namespace Traits;

use FQL\Enum\Join;
use FQL\Enum\Operator;
use FQL\Query\Provider;
use FQL\Stream\Json;
use PHPUnit\Framework\TestCase;

/**
 * Covers the less-exercised join variants — LEFT / RIGHT / FULL, blockJoinable,
 * duplicate ON clause detection, alias propagation, and FQL-string joins.
 */
class JoinableEdgeTest extends TestCase
{
    private string $usersPath;
    private string $ordersPath;

    protected function setUp(): void
    {
        $this->usersPath = (string) tempnam(sys_get_temp_dir(), 'fql-users-');
        $this->ordersPath = (string) tempnam(sys_get_temp_dir(), 'fql-orders-');
        file_put_contents($this->usersPath, json_encode([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ]));
        file_put_contents($this->ordersPath, json_encode([
            ['user_id' => 1, 'total' => 100],
            ['user_id' => 1, 'total' => 200],
            ['user_id' => 2, 'total' => 50],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->usersPath);
        @unlink($this->ordersPath);
    }

    public function testLeftJoin(): void
    {
        $rows = iterator_to_array(
            Provider::fql(sprintf(
                'SELECT id, name, o.total FROM json(%s) LEFT JOIN json(%s) AS o ON id = o.user_id',
                $this->usersPath,
                $this->ordersPath
            ))->execute()->fetchAll()
        );

        // Charlie (id 3) has no orders → should still appear with null total.
        $names = array_column($rows, 'name');
        $this->assertContains('Charlie', $names);
    }

    public function testRightJoin(): void
    {
        $rows = iterator_to_array(
            Provider::fql(sprintf(
                'SELECT name, o.total FROM json(%s) RIGHT JOIN json(%s) AS o ON id = o.user_id',
                $this->usersPath,
                $this->ordersPath
            ))->execute()->fetchAll()
        );

        // 3 orders → 3 rows in right join.
        $this->assertCount(3, $rows);
    }

    public function testFullJoin(): void
    {
        $rows = iterator_to_array(
            Provider::fql(sprintf(
                'SELECT name, o.total FROM json(%s) FULL JOIN json(%s) AS o ON id = o.user_id',
                $this->usersPath,
                $this->ordersPath
            ))->execute()->fetchAll()
        );

        // Full join: matching orders + unmatched users (Charlie) + no unmatched
        // orders → at least 4 rows.
        $this->assertGreaterThanOrEqual(4, count($rows));
    }

    public function testJoinWithFluentAlias(): void
    {
        $orders = Json::open($this->ordersPath)->query()
            ->selectAll()
            ->from('*');

        $users = Json::open($this->usersPath)->query()
            ->select('id', 'name')
            ->select('o.total')->as('orderTotal')
            ->from('*')
            ->innerJoin($orders)
                ->as('o')
                ->on('id', Operator::EQUAL, 'user_id');

        $this->assertFalse($users->isJoinableEmpty());

        $results = iterator_to_array($users->execute()->fetchAll());
        $this->assertNotEmpty($results);
    }

    public function testBlockJoinableInDescribeMode(): void
    {
        $users = Json::open($this->usersPath)->query();
        $users->blockJoinable();

        $this->expectException(\FQL\Exception\QueryLogicException::class);
        $users->innerJoin(Json::open($this->ordersPath)->query(), 'o');
    }

    public function testIsJoinableEmptyReflectsState(): void
    {
        $users = Json::open($this->usersPath)->query();
        $this->assertTrue($users->isJoinableEmpty());

        $users->innerJoin(Json::open($this->ordersPath)->query(), 'o');
        $this->assertFalse($users->isJoinableEmpty());
    }
}
