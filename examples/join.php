<?php

use FQL\Enum\Operator;
use FQL\Query;

require __DIR__ . '/bootstrap.php';

try {
    $users = Query\Provider::fromFileQuery('(./examples/data/users.json).data.users')
        ->select('id, name')
        ->select('o.id')->as('orderId')
        ->select('o.total_price')->as('totalPrice')
        ->leftJoin(Query\Provider::fromFileQuery('(./examples/data/orders.xml).orders.order'), 'o')
            ->on('id', Operator::EQUAL, 'user_id')
        ->groupBy('o.id')
        ->orderBy('totalPrice')->desc();

    Query\Debugger::inspectQuery($users);
    Query\Debugger::benchmarkQuery($users, 100);
    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoSection($e::class);
    Query\Debugger::echoLine($e->getMessage());
    Query\Debugger::dump($e->getTraceAsString());
    Query\Debugger::split();
}
