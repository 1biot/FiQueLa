<?php

use FQL\Enum\Operator as Op;
use FQL\Query;
use FQL\Stream;

require __DIR__ . '/bootstrap.php';

try {
    $json = Stream\Json::open(__DIR__ . '/data/products.json');

    // --- Fluent API: UNION (deduplicates) ---
    $cheapProducts = $json->query()
        ->select('id', 'name', 'price')
        ->from('data.products')
        ->where('price', Op::LESS_THAN_OR_EQUAL, 100);

    $expensiveProducts = $json->query()
        ->select('id', 'name', 'price')
        ->from('data.products')
        ->where('price', Op::GREATER_THAN_OR_EQUAL, 400);

    $union = $cheapProducts->union($expensiveProducts);

    Query\Debugger::echoSection('Fluent API: UNION (cheap <= 100 + expensive >= 400)');
    Query\Debugger::inspectQuery($union, true);

    // --- Fluent API: UNION ALL (keeps duplicates) ---
    $midRange = $json->query()
        ->select('id', 'name', 'price')
        ->from('data.products')
        ->where('price', Op::GREATER_THAN_OR_EQUAL, 300);

    $midRange2 = $json->query()
        ->select('id', 'name', 'price')
        ->from('data.products')
        ->where('price', Op::GREATER_THAN_OR_EQUAL, 300);

    $unionAll = $midRange->unionAll($midRange2);

    Query\Debugger::echoSection('Fluent API: UNION ALL (price >= 300, duplicates kept)');
    Query\Debugger::inspectQuery($unionAll, true);

    // --- Fluent API: chained UNION + UNION ALL ---
    $q1 = $json->query()
        ->select('id', 'name', 'price')
        ->from('data.products')
        ->where('id', Op::EQUAL, 1);

    $q2 = $json->query()
        ->select('id', 'name', 'price')
        ->from('data.products')
        ->where('id', Op::EQUAL, 3);

    $q3 = $json->query()
        ->select('id', 'name', 'price')
        ->from('data.products')
        ->where('id', Op::EQUAL, 5);

    $chained = $q1->union($q2)->unionAll($q3);

    Query\Debugger::echoSection('Fluent API: chained UNION + UNION ALL');
    Query\Debugger::inspectQuery($chained, true);

    // --- FQL string: UNION ---
    $jsonPath = realpath(__DIR__ . '/data/products.json');

    $fqlUnion = sprintf(
        'SELECT id, name, price FROM [json](%s).data.products WHERE price <= 100 UNION SELECT id, name, price FROM [json](%s).data.products WHERE price >= 400',
        $jsonPath,
        $jsonPath
    );

    Query\Debugger::echoSection('FQL: UNION');
    $query = Query\Debugger::inspectSql($fqlUnion);
    Query\Debugger::inspectQuery($query, true);

    // --- FQL string: UNION ALL ---
    $fqlUnionAll = sprintf(
        'SELECT id, name, price FROM [json](%s).data.products WHERE price >= 300 UNION ALL SELECT id, name, price FROM [json](%s).data.products WHERE price >= 300',
        $jsonPath,
        $jsonPath
    );

    Query\Debugger::echoSection('FQL: UNION ALL');
    $query = Query\Debugger::inspectSql($fqlUnionAll);
    Query\Debugger::inspectQuery($query, true);

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
