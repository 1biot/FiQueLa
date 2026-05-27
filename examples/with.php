<?php

use FQL\Enum\Operator as Op;
use FQL\Query;
use FQL\Stream;

require __DIR__ . '/bootstrap.php';

try {
    $json = Stream\Json::open(__DIR__ . '/data/products.json');

    // --- Fluent API: register a CTE and reuse it in a JOIN ---
    $expensive = $json->query()
        ->select('id', 'name')
        ->from('data.products')
        ->where('price', Op::GREATER_THAN_OR_EQUAL, 300);

    // with() returns $this so the registration can sit anywhere in the chain.
    $main = $json->query()->with('expensive', $expensive);
    $cte = $main->getCte('expensive');

    $fluentJoin = $json->query()
        ->select('p.id', 'p.name')
        ->from('data.products')
        ->innerJoin($cte, 'p')
        ->on('id', Op::EQUAL, 'p.id');

    Query\Debugger::echoSection('Fluent API: with() + INNER JOIN over the named CTE');
    Query\Debugger::inspectQuery($fluentJoin, true);

    // --- FQL: WITH ... SELECT ... FROM cte_name ---
    $jsonPath = realpath(__DIR__ . '/data/products.json');

    $fqlSimple = sprintf(
        'WITH cheap AS (SELECT id, name, price FROM json(%s).data.products WHERE price <= 200) '
        . 'SELECT name, price FROM cheap',
        $jsonPath
    );

    Query\Debugger::echoSection('FQL: WITH + FROM cte');
    $query = Query\Debugger::inspectSql($fqlSimple);
    Query\Debugger::inspectQuery($query, true);

    // --- FQL: multiple CTEs with forward-chaining ---
    $fqlChain = sprintf(
        'WITH cheap AS (SELECT id, name, price FROM json(%s).data.products WHERE price <= 200), '
        . '     names AS (SELECT name FROM cheap) '
        . 'SELECT name FROM names',
        $jsonPath
    );

    Query\Debugger::echoSection('FQL: forward-chained CTEs (names is built on top of cheap)');
    $query = Query\Debugger::inspectSql($fqlChain);
    Query\Debugger::inspectQuery($query, true);

    // --- FQL: CTE referenced twice — materialised once, shared between references ---
    $fqlSelfJoin = sprintf(
        'WITH small AS (SELECT id, name FROM json(%s).data.products WHERE id <= 3) '
        . 'SELECT s1.name FROM small AS s1 INNER JOIN small AS s2 ON id = s2.id',
        $jsonPath
    );

    Query\Debugger::echoSection('FQL: self-JOIN over a CTE — single materialisation, two references');
    $query = Query\Debugger::inspectSql($fqlSelfJoin);
    Query\Debugger::inspectQuery($query, true);

    // --- FQL: EXPLAIN reveals the materialised CTE as a results(memory) source ---
    $fqlExplain = sprintf(
        'EXPLAIN WITH active AS (SELECT id, name FROM json(%s).data.products WHERE price > 0) '
        . 'SELECT name FROM active',
        $jsonPath
    );

    Query\Debugger::echoSection('FQL: EXPLAIN of a WITH statement');
    $query = Query\Debugger::inspectSql($fqlExplain);
    Query\Debugger::inspectQuery($query, true);

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
