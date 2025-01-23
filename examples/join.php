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

    $sql = <<<SQL
SELECT
    id,
    name,
    o.id AS orderId,
    o.total_price AS totalPrice
FROM [json](./examples/data/users.json).data.users
LEFT JOIN
    (./examples/data/orders.xml).orders.order AS o
        ON id = user_id
GROUP BY o.id
ORDER BY totalPrice DESC
SQL;

    $query = Query\Debugger::inspectSql($sql);
    Query\Debugger::inspectQuery($query);
    Query\Debugger::benchmarkQuery($query, 100);
    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
