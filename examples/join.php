<?php

use FQL\Enum\Operator;
use FQL\Query;

require __DIR__ . '/bootstrap.php';

try {
    // 1. Original example — JOIN with alias as parameter
    $users = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->select('id, name')
        ->select('o.id')->as('orderId')
        ->select('o.total_price')->as('totalPrice')
        ->leftJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'), 'o')
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
FROM json(./examples/data/users.json).data.users
LEFT JOIN
    xml(./examples/data/orders.xml).orders.order AS o
        ON id = user_id
GROUP BY o.id
ORDER BY totalPrice DESC
SQL;

    $query = Query\Debugger::inspectSql($sql);
    Query\Debugger::inspectQuery($query);
    Query\Debugger::benchmarkQuery($query, 100);

    // 2. Fluent JOIN alias — using ->as() instead of parameter
    $usersStream = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users');
    $ordersStream = Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order');

    $fluentJoin = $usersStream
        ->select('id', 'name')
        ->select('o.id')->as('orderId')
        ->select('o.total_price')->as('totalPrice')
        ->leftJoin($ordersStream)->as('o')
            ->on('id', Operator::EQUAL, 'user_id')
        ->orderBy('totalPrice')->desc();

    Query\Debugger::inspectQuery($fluentJoin);

    // 3. FROM alias with aliased wildcard
    $fromAlias = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->from('data.users')->as('u')
        ->select('u.*')
        ->leftJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o')
            ->on('u.id', Operator::EQUAL, 'user_id')
        ->select('o.total_price')->as('totalPrice')
        ->orderBy('totalPrice')->desc();

    Query\Debugger::inspectQuery($fromAlias);

    // 4. FQL with FROM alias
    $sqlFromAlias = <<<SQL
SELECT
    u.name,
    o.id AS orderId,
    o.total_price AS totalPrice
FROM json(./examples/data/users.json).data.users AS u
LEFT JOIN
    xml(./examples/data/orders.xml).orders.order AS o
        ON u.id = user_id
ORDER BY totalPrice DESC
SQL;

    $queryFromAlias = Query\Debugger::inspectSql($sqlFromAlias);
    Query\Debugger::inspectQuery($queryFromAlias);

    // 5. Multiple JOINs with fluent aliases
    $multiJoin = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->select('name')
        ->select('o.id')->as('orderId')
        ->select('o.total_price')->as('totalPrice')
        ->leftJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o')
            ->on('id', Operator::EQUAL, 'user_id')
        ->leftJoin(Query\Provider::fromFileQuery('json(./examples/data/products.json).data.products'))->as('p')
            ->on('id', Operator::EQUAL, 'p.id')
        ->select('p.name')->as('productName')
        ->limit(5);

    Query\Debugger::inspectQuery($multiJoin);

    // 6. Ambiguous field detection — o.* would conflict with 'id' from users
    try {
        $ambiguous = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
            ->select('id', 'name')
            ->select('o.*')
            ->leftJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o')
                ->on('id', Operator::EQUAL, 'user_id')
            ->limit(1);

        iterator_to_array($ambiguous->execute()->fetchAll());
    } catch (\FQL\Exception\SelectException $e) {
        echo "Ambiguous field detected (expected): " . $e->getMessage() . PHP_EOL;
    }

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
