<?php

require __DIR__ . '/bootstrap.php';

use FQL\Query\Debugger;
use FQL\Stream\Csv;

$utf8 = Csv::open(__DIR__ . '/data/products-utf-8.csv')
    ->useHeader(true);

$windows1250 = Csv::open(__DIR__ . '/data/products-w-1250.csv')
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

Debugger::inspectQuery($query);
Debugger::benchmarkQuery($query);

Debugger::end();
