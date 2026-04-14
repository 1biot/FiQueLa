<?php

use FQL\Enum\Operator;
use FQL\Query;

require __DIR__ . '/bootstrap.php';

try {
    Query\Debugger::start();

    // 1. INNER JOIN — only users that have orders (alias as parameter)
    Query\Debugger::echoSection('1. INNER JOIN with alias as parameter', 'yellow');
    $users = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->select('id, name')
        ->select('o.id')->as('orderId')
        ->select('o.total_price')->as('totalPrice')
        ->innerJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'), 'o')
            ->on('id', Operator::EQUAL, 'user_id')
        ->groupBy('o.id')
        ->orderBy('totalPrice')->desc();

    Query\Debugger::inspectQuery($users, true);
    Query\Debugger::benchmarkQuery($users, 100);

    // 1b. Same INNER JOIN via FQL string
    Query\Debugger::echoSection('1b. Same INNER JOIN via FQL string', 'yellow');
    $query = Query\Debugger::inspectSql(<<<SQL
SELECT
    id, name,
    o.id AS orderId,
    o.total_price AS totalPrice
FROM json(./examples/data/users.json).data.users
INNER JOIN
    xml(./examples/data/orders.xml).orders.order AS o
        ON id = user_id
GROUP BY o.id
ORDER BY totalPrice DESC
SQL);
    Query\Debugger::inspectQuery($query, true);

    // 2. LEFT JOIN — all users, even without orders (fluent alias)
    Query\Debugger::echoSection('2. LEFT JOIN with fluent ->as()', 'yellow');
    $leftJoin = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->select('id', 'name')
        ->select('o.id')->as('orderId')
        ->select('o.total_price')->as('totalPrice')
        ->leftJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o')
            ->on('id', Operator::EQUAL, 'user_id')
        ->orderBy('totalPrice')->desc();

    Query\Debugger::inspectQuery($leftJoin, true);

    // 3. RIGHT JOIN — all orders, even if user is missing
    Query\Debugger::echoSection('3. RIGHT JOIN — all orders', 'yellow');
    $rightJoin = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->select('name')
        ->select('o.id')->as('orderId')
        ->select('o.total_price')->as('totalPrice')
        ->rightJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o')
            ->on('id', Operator::EQUAL, 'user_id')
        ->orderBy('totalPrice')->desc();

    Query\Debugger::inspectQuery($rightJoin, true);

    // 4. FULL JOIN via FQL string — all users + all orders
    Query\Debugger::echoSection('4. FULL JOIN via FQL string', 'yellow');
    $fullJoinQuery = Query\Debugger::inspectSql(<<<SQL
SELECT
    name,
    o.id AS orderId,
    o.total_price AS totalPrice
FROM json(./examples/data/users.json).data.users
FULL JOIN
    xml(./examples/data/orders.xml).orders.order AS o
        ON id = user_id
ORDER BY totalPrice DESC
SQL);
    Query\Debugger::inspectQuery($fullJoinQuery, true);

    // 5. FROM alias with u.* wildcard + INNER JOIN
    Query\Debugger::echoSection('5. FROM alias with u.* wildcard + INNER JOIN', 'yellow');
    $fromAlias = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->from('data.users')->as('u')
        ->select('u.*')
        ->select('o.total_price')->as('totalPrice')
        ->innerJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o')
            ->on('u.id', Operator::EQUAL, 'user_id')
        ->orderBy('totalPrice')->desc();

    Query\Debugger::inspectQuery($fromAlias, true);

    // 6. Multiple JOINs — INNER + LEFT with different aliases
    Query\Debugger::echoSection('6. Multiple JOINs — INNER + LEFT', 'yellow');
    $multiJoin = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->from('data.users')->as('u')
        ->select('u.name')
        ->select('o1.id')->as('firstOrderId')
        ->select('o1.total_price')->as('firstOrderPrice')
        ->select('o2.id')->as('secondOrderId')
        ->select('o2.total_price')->as('secondOrderPrice')
        ->innerJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o1')
            ->on('u.id', Operator::EQUAL, 'user_id')
        ->leftJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o2')
            ->on('u.id', Operator::EQUAL, 'user_id')
        ->where('o1.id', Operator::NOT_EQUAL, 'o2.id')
        ->limit(5);

    Query\Debugger::inspectQuery($multiJoin, true);

    // 7. Subquery JOIN — fluent API (LEFT JOIN with filtered subquery)
    Query\Debugger::echoSection('7. Subquery LEFT JOIN (fluent API)', 'yellow');
    $filteredOrders = Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order')
        ->select('id', 'user_id', 'total_price')
        ->where('total_price', Operator::GREATER_THAN, 200);

    $subqueryJoin = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->select('id', 'name')
        ->select('o.id')->as('orderId')
        ->select('o.total_price')->as('totalPrice')
        ->leftJoin($filteredOrders)->as('o')
            ->on('id', Operator::EQUAL, 'user_id')
        ->orderBy('totalPrice')->desc();

    Query\Debugger::inspectQuery($subqueryJoin, true);

    // 7b. Same subquery JOIN via FQL string (INNER JOIN)
    Query\Debugger::echoSection('7b. Subquery INNER JOIN (FQL string)', 'yellow');
    $sqlSubquery = Query\Debugger::inspectSql(<<<SQL
SELECT
    id, name,
    o.id AS orderId,
    o.total_price AS totalPrice
FROM json(./examples/data/users.json).data.users
INNER JOIN
    (SELECT id, user_id, total_price FROM xml(./examples/data/orders.xml).orders.order WHERE total_price > 200) AS o
        ON id = user_id
ORDER BY totalPrice DESC
SQL);
    Query\Debugger::inspectQuery($sqlSubquery, true);

    // 8. Ambiguous field detection
    Query\Debugger::echoSection('8. Ambiguous field detection', 'yellow');
    try {
        $ambiguous = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
            ->select('id', 'name')
            ->select('o.*')
            ->innerJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o')
                ->on('id', Operator::EQUAL, 'user_id')
            ->limit(1);

        // users has 'id', orders also has 'id' — o.* would overwrite it
        iterator_to_array($ambiguous->execute()->fetchAll());
        Query\Debugger::echoSection('ERROR: Should have thrown SelectException', 'red');
    } catch (\FQL\Exception\SelectException $e) {
        Query\Debugger::echoSection('Caught expected error', 'green');
        echo "> " . $e->getMessage() . PHP_EOL;
    }

    // Resolution: use explicit fields or drop the conflicting field from select
    Query\Debugger::echoSection('8b. Resolved — no id conflict with o.*', 'green');
    $resolved = Query\Provider::fromFileQuery('json(./examples/data/users.json).data.users')
        ->select('name')
        ->select('o.*')
        ->leftJoin(Query\Provider::fromFileQuery('xml(./examples/data/orders.xml).orders.order'))->as('o')
            ->on('id', Operator::EQUAL, 'user_id')
        ->limit(3);

    Query\Debugger::inspectQuery($resolved, true);

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
