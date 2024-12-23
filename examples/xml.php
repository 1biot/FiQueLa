<?php

use UQL\Enum\Operator;
use UQL\Stream\Xml;

require __DIR__ . '/../vendor/autoload.php';

$xml = Xml::open(__DIR__ . '/data/products.xml');

$query = $xml->query();
$query->select('name, price')
    ->select('brand.name')->as('brand')
    ->from('root.item')
    ->where('price', Operator::GREATER_THAN, 100);

dump($query->test());
dump(iterator_to_array($query->fetchAll()));
