<?php

use UQL\Enum\Operator;
use UQL\Query\Debugger;
use UQL\Stream\Json;

require __DIR__ . '/bootstrap.php';

$json = Json::open(__DIR__ . '/data/products.json');
$query = $json->query();
$query->select('name, price')
    ->select('brand.name')->as('brand')
    ->from('data.products')
    ->where('brand.code', Operator::EQUAL, 'BRAND-A')
    ->orGroup()
        ->where('name', Operator::EQUAL, 'Product B')
        ->and('price', Operator::GREATER_THAN, 200);

Debugger::inspectQuery($query);
Debugger::benchmarkQuery($query);

$query = $json->query();
$query->select('name, price')
    ->select('brand.name')->as('brand')
    ->from('data.products')
    ->where('price', Operator::GREATER_THAN_OR_EQUAL, 100)
    ->and('price', Operator::LESS_THAN_OR_EQUAL, 200)
    ->or('brand.code', Operator::EQUAL, 'BRAND-B');

Debugger::inspectQuery($query);
Debugger::benchmarkQuery($query);

Debugger::end();
