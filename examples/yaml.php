<?php

use FQL\Enum\Operator as Op;
use FQL\Query;
use FQL\Stream;

require __DIR__ . '/bootstrap.php';

try {
    $query = Query\Provider::fromFileQuery('(./examples/data/products.yaml).data.paginator')
        ->select('totalCount')->as('totalPages')
        ->select('page')->as('actualPage');

    Query\Debugger::inspectQuery($query);
    Query\Debugger::benchmarkQuery($query);

    $yaml = Stream\Provider::fromFile('./examples/data/products.yaml');
    $query = $yaml->query();
    $query->select('name, price')
        ->select('brand.name')->as('brand')
        ->from('data.products')
        ->where('brand.code', Op::EQUAL, 'BRAND-A')
        ->orGroup()
        ->where('name', Op::EQUAL, 'Product 2')
        ->and('price', Op::GREATER_THAN, 200);

    Query\Debugger::inspectQuery($query);
    Query\Debugger::benchmarkQuery($query);
    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
