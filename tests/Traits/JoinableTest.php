<?php

namespace Traits;

use PHPUnit\Framework\TestCase;
use FQL\Enum\Operator;
use FQL\Stream\Json;
use FQL\Stream\Xml;

class JoinableTest extends TestCase
{
    private Json $json;
    private Xml $xml;

    protected function setUp(): void
    {
        $this->json = Json::open(realpath(__DIR__ . '/../../examples/data/users.json'));
        $this->xml = Xml::open(realpath(__DIR__ . '/../../examples/data/orders.xml'));
    }

    public function testInnerJoin(): void
    {
        $orders = $this->xml->query()
            ->select('id')->as('orderId')
            ->select('user_id')->as('userId')
            ->select('total_price')->as('totalPrice')
            ->from('orders.order');

        $query = $this->json->query()
            ->select('id, name')
            ->select('o.orderId')->as('orderId')
            ->select('o.totalPrice')->as('totalPrice')
            ->from('data.users')
            ->innerJoin($orders, 'o')
                ->on('id', Operator::EQUAL, 'userId')
            ->where('o.totalPrice', Operator::GREATER_THAN, 200)
            ->orderBy('totalPrice')->desc();

        $results = $query->execute();
        $count = $results->count();

        self::assertSame(count(iterator_to_array($results->fetchAll())), $count);
    }
}
