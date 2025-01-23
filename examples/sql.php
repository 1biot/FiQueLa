<?php

use FQL\Query;
use FQL\Stream;

require __DIR__ . '/bootstrap.php';

try {
    $xml = Stream\Xml::open(__DIR__ . '/data/products.xml');
    $sql = <<<SQL
SELECT DISTINCT @attributes.id AS productId, name, price, brand
FROM root.item
WHERE brand.code == "BRAND-A" OR price >= 200
ORDER BY productId DESC, price ASC
SQL;

    $query = Query\Debugger::inspectStreamSql($xml, $sql);
    Query\Debugger::benchmarkQuery($query);

    $jsonSql = <<<SQL
SELECT *
FROM (./examples/data/products.json).data.products
WHERE
    brand.code == 'BRAND-A'
    OR name == 'Product C'
    OR price > 300
SQL;

    $query = Query\Debugger::inspectSql($jsonSql);
    Query\Debugger::benchmarkQuery($query);
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}

Query\Debugger::end();
