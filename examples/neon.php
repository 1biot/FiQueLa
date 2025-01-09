<?php

use UQL\Enum\Operator;
use UQL\Helpers\Debugger;
use UQL\Stream\Neon;

require __DIR__ . '/bootstrap.php';

$neon = Neon::open(__DIR__ . '/data/products.neon');

$query = $neon->query()
    ->select('name, price')
    ->select('manufacturer')->as('brand')
    ->from('data.products')
    ->where('manufacturer', Operator::EQUAL, 'Manufacturer 3')
    ->or('name', Operator::EQUAL, 'Product 2')
    ->or('price', Operator::GREATER_THAN, 200);

Debugger::inspectQuery($query);
Debugger::benchmarkQuery($query);

$query = $neon->query()
    ->select('totalCount')->as('totalPages')
    ->select('page')->as('actualPage')
    ->from('data.paginator');

Debugger::inspectQuery($query);
Debugger::benchmarkQuery($query);

Debugger::end();
