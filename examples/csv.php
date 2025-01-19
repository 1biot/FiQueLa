<?php

require __DIR__ . '/bootstrap.php';

use FQL\Enum;
use FQL\Query;

try {
    $utf8 = Query\Provider::fromFile('./examples/data/products-utf-8.csv', Enum\Format::CSV)
        ->useHeader(true);

    $windows1250 = Query\Provider::fromFile('./examples/data/products-w-1250.csv', Enum\Format::CSV)
        ->setInputEncoding('windows-1250')
        ->setDelimiter(';')
        ->useHeader(true);

    $query = $windows1250->query()
        ->select('ean')
        ->select('defaultCategory')
        ->explode('defaultCategory', ' > ')->as('categoryArray')
        ->select('price')
        ->round('price', 2)->as('price_rounded')
        ->modulo('price', 100)->as('modulo_100')
        ->modulo('price', 54)->as('modulo_54')
        ->groupBy('defaultCategory');

    Query\Debugger::inspectQuery($query);
    Query\Debugger::benchmarkQuery($query);

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoSection($e::class);
    Query\Debugger::echoLine($e->getMessage());
    Query\Debugger::dump($e->getTraceAsString());
    Query\Debugger::split();
}
