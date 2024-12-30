<?php

use UQL\Enum\Operator;
use UQL\Helpers\Debugger;
use UQL\Stream\Xml;

require __DIR__ . '/bootstrap.php';

$xml = Xml::open(__DIR__ . '/data/products.xml');

$query = $xml->query();
$query->select('name, price')
    ->select('brand.name')->as('brand')
    ->from('root.item')
    ->where('brand.code', Operator::EQUAL_STRICT, "BRAND-A")
    ->orGroup()
        ->or('price', Operator::LESS_THAN, 300)
        ->and('price', Operator::GREATER_THAN_OR_EQUAL, 200)
    ->endGroup();

Debugger::inspectQuery($query);
Debugger::finish();
