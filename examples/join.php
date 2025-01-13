<?php

use FQL\Enum\Operator;
use FQL\Query\Debugger;
use FQL\Stream\Json;
use FQL\Stream\Xml;

require __DIR__ . '/bootstrap.php';

$usersFile = Json::open(__DIR__ . '/data/users.json');
$ordersFile = Xml::open(__DIR__ . '/data/orders.xml');

$orders = $ordersFile->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order');

$users = $usersFile->query()
    ->select('id, name')
    ->select('o.orderId')->as('orderId')
    ->select('o.totalPrice')->as('totalPrice')
    ->from('data.users')
    ->leftJoin($orders, 'o')
        ->on('id', Operator::EQUAL, 'userId')
    ->groupBy('o.orderId')
    ->orderBy('totalPrice')->desc();

Debugger::inspectQuery($users);
Debugger::benchmarkQuery($users, 100);
Debugger::end();
