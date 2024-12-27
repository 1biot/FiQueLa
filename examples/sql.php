<?php

use UQL\Stream\Xml;
use UQL\Stream\Json;

require __DIR__ . '/../vendor/autoload.php';

$xml = Xml::open(__DIR__ . '/data/products.xml');

$sql = <<<SQL
SELECT
    name, price, brand
FROM root.item
WHERE brand.code == "BRAND-A"
OR price >= 200
ORDER BY name DESC
SQL;

$result = iterator_to_array($xml->sql($sql));
dump($result);

$json = Json::open(__DIR__ . '/data/products.json');

$jsonSql = <<<SQL
SELECT *
FROM data.products
WHERE
    brand.code == 'BRAND-A'
    OR name == 'Product C'
    OR price > 300
SQL;

$result = $json->sql($jsonSql);
dump(iterator_to_array($result));
