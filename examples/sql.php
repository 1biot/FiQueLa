<?php

use UQL\Helpers\Debugger;
use UQL\Stream\Xml;
use UQL\Stream\Json;

require __DIR__ . '/bootstrap.php';

$xml = Xml::open(__DIR__ . '/data/products.xml');

$sql = <<<SQL
SELECT
    @attributes.id AS productId, name, price, brand
FROM root.item
WHERE brand.code == "BRAND-A"
OR price >= 200
ORDER BY productId DESC
SQL;

Debugger::inspectQuerySql($xml, $sql);

$json = Json::open(__DIR__ . '/data/products.json');
$jsonSql = <<<SQL
SELECT *
FROM data.products
WHERE
    brand.code == 'BRAND-A'
    OR name == 'Product C'
    OR price > 300
SQL;

Debugger::inspectQuerySql($json, $jsonSql);
Debugger::finish();
