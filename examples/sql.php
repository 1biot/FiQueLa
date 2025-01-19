<?php

use FQL\Enum\Operator;
use FQL\Query\Debugger;
use FQL\Stream\Json;
use FQL\Stream\Xml;

require __DIR__ . '/bootstrap.php';

$xml = Xml::open(__DIR__ . '/data/products.xml');

$sql = <<<SQL
SELECT DISTINCT @attributes.id AS productId, name, price, brand
FROM root.item
WHERE brand.code == "BRAND-A" OR price >= 200
ORDER BY productId DESC, price ASC
SQL;

$query = Debugger::inspectQuerySql($xml, $sql);
Debugger::benchmarkQuery($query);

$query->whereGroup()
    ->where('name', Operator::EQUAL, 'Product B')
    ->or('price', Operator::GREATER_THAN, 300);
Debugger::inspectQuery($query);die;

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
