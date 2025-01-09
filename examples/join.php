<?php

use UQL\Enum\Operator;
use UQL\Helpers\Debugger;
use UQL\Stream\Json;
use UQL\Stream\Xml;

require __DIR__ . '/bootstrap.php';

$usersFile = Json::open(__DIR__ . '/data/users.json');
$ordersFile = Xml::open(__DIR__ . '/data/orders.xml');

$orders = $ordersFile->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order');

Debugger::inspectQuery($orders);

$users = $usersFile->query()
    ->select('id, name')
    ->select('o.orderId')->as('orderId')
    ->select('o.totalPrice')->as('totalPrice')
    ->from('data.users')
    ->leftJoin($orders, 'o')
        ->on('id', Operator::EQUAL, 'userId')
    ->groupBy('orderId')
    ->orderBy('totalPrice')->desc();

Debugger::inspectQuery($users);
Debugger::benchmarkQuery($users, 10000);
Debugger::end();
