<?php

require __DIR__ . '/bootstrap.php';


use FQL\Enum\Operator;
use FQL\Query;
use FQL\Query\Provider;

require __DIR__ . '/bootstrap.php';

try {
    $users = Query\Provider::fromFileQuery('(./examples/data/users.json).data.users')
        ->explainAnalyze()
        ->select('id, name')
        ->select('o.id')->as('orderId')
        ->select('o.total_price')->as('totalPrice')
        ->leftJoin(Query\Provider::fromFileQuery('(./examples/data/orders.xml).orders.order'), 'o')
        ->on('id', Operator::EQUAL, 'user_id')
        ->groupBy('o.id')
        ->orderBy('totalPrice')->desc();


    Query\Debugger::queryToOutput($users);
    Query\Debugger::dump(iterator_to_array($users->execute()->fetchAll()));

    $sql = <<<SQL
EXPLAIN ANALYZE
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

    Query\Debugger::queryToOutput($users);
    Provider::fql($sql);
    Query\Debugger::dump(iterator_to_array($users->execute()->fetchAll()));
    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}

