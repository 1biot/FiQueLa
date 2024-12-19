<?php

use JQL\Enum\Operator;
use JQL\Json;

require __DIR__ . '/../vendor/autoload.php';

$json = Json::open(__DIR__ . '/products.json');

$query = $json->query();
$query->setGrouping(false)
    ->select('name, price, brand')
    ->from('data.products')
    ->where('brand.code', Operator::EQUAL, 'BRAND-A')
    ->or('name', Operator::EQUAL, 'Product B')
    ->or('price', Operator::GREATER_THAN, 200);
dump($query->test());

$query = $json->query();
$query->setGrouping(true)
    ->select('name, price, brand')
    ->from('data.products')
    ->where('price', Operator::GREATER_THAN_OR_EQUAL, 100)
    ->and('price', Operator::LESS_THAN_OR_EQUAL, 200)
    ->or('brand.code', Operator::EQUAL, 'BRAND-B');
dump($query->test());
