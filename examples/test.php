<?php

use FQL\Enum\Operator as Op;
use FQL\Query;

require __DIR__ . '/bootstrap.php';

try {
    $json = Query\Provider::fromFileQuery('[json](./examples/data/products.tmp)');

    $jsonQ = $json->query()
        ->select('name, price')
        ->select('brand.name')->as('brand')
        ->from('data.products')
        ->where('brand.code', Op::EQUAL, 'BRAND-A')
        ->orGroup()
            ->where('name', Op::EQUAL, 'Product B')
            ->and('price', Op::GREATER_THAN, 200);

    Query\Debugger::inspectQuery($jsonQ);
    Query\Debugger::benchmarkQuery($jsonQ);

    $jsonQ = $json->query();
    $jsonQ->select('name, price')
        ->select('brand.name')->as('brand')
        ->from('data.products')
        ->where('price', Op::GREATER_THAN_OR_EQUAL, 100)
        ->and('price', Op::LESS_THAN_OR_EQUAL, 200)
        ->or('brand.code', Op::EQUAL, 'BRAND-B');

    Query\Debugger::inspectQuery($jsonQ);
    Query\Debugger::benchmarkQuery($jsonQ);

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoSection($e::class);
    Query\Debugger::echoLine($e->getMessage());
    Query\Debugger::dump($e->getTraceAsString());
    Query\Debugger::split();
}
