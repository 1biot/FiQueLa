<?php

use FQL\Enum\Operator;
use FQL\Query\Debugger;
use FQL\Stream\Yaml;

require __DIR__ . '/bootstrap.php';

$yaml = Yaml::open(__DIR__ . '/data/products.yaml');

$query = $yaml->query();
$query->select('name, price')
    ->select('brand.name')->as('brand')
    ->from('data.products')
    ->where('brand.code', Operator::EQUAL, 'BRAND-A')
    ->orGroup()
        ->where('name', Operator::EQUAL, 'Product 2')
        ->and('price', Operator::GREATER_THAN, 200);

Debugger::inspectQuery($query);
Debugger::benchmarkQuery($query);

$query = $yaml->query()
    ->select('totalCount')->as('totalPages')
    ->select('page')->as('actualPage')
    ->from('data.paginator');

Debugger::inspectQuery($query);
Debugger::benchmarkQuery($query);

Debugger::end();
