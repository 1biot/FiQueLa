<?php

use UQL\Enum\Operator;
use UQL\Stream\Json;
use UQL\Stream\Xml;

require __DIR__ . '/../vendor/autoload.php';

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
    ->innerJoin($orders, 'o')
        ->on('id', Operator::EQUAL, 'userId')
    ->having('totalPrice', Operator::GREATER_THAN, 200)
    ->orderBy('totalPrice')->desc();

dump($users->test());
dump($users->count());
dump(iterator_to_array($users->fetchAll()));
