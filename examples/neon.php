<?php

use FQL\Enum\Operator as Op;
use FQL\Query;
use FQL\Stream;

require __DIR__ . '/bootstrap.php';

try {
    $query = Query\Provider::fromFileQuery('(./examples/data/products.neon).data.paginator')
        ->select('totalCount')->as('totalPages')
        ->select('page')->as('actualPage')
        ->from('data.paginator');

    Query\Debugger::inspectQuery($query);
    Query\Debugger::benchmarkQuery($query);

    $neon = Stream\Provider::fromFile('./examples/data/products.neon');
    $query = $neon->query()
        ->select('name, price')
        ->select('manufacturer')->as('brand')
        ->from('data.products')
        ->where('manufacturer', Op::EQUAL, 'Manufacturer 3')
        ->or('name', Op::EQUAL, 'Product 2')
        ->or('price', Op::GREATER_THAN, 200);

    Query\Debugger::inspectQuery($query);
    Query\Debugger::benchmarkQuery($query);

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
