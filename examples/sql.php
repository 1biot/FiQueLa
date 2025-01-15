<?php

use FQL\Query\Debugger;
use FQL\Stream\Json;
use FQL\Stream\Xml;

require __DIR__ . '/bootstrap.php';

$xml = Xml::open(__DIR__ . '/data/products.xml');

$sql = <<<SQL
SELECT @attributes.id AS productId, name, price, brand
FROM root.item
WHERE brand.code == "BRAND-A" OR price >= 200
ORDER BY productId DESC
SQL;

$query = Debugger::inspectQuerySql($xml, $sql);
Debugger::benchmarkQuery($query);

$json = Json::open(__DIR__ . '/data/products.json');
$jsonSql = <<<SQL
SELECT *
FROM data.products
WHERE
    brand.code == 'BRAND-A'
    OR name == 'Product C'
    OR price > 300
SQL;

$query = Debugger::inspectQuerySql($json, $jsonSql);
Debugger::benchmarkQuery($query);

Debugger::end();
