<?php

use JQL\Enum\Operator;
use JQL\Stream\Neon;

require __DIR__ . '/../vendor/autoload.php';

$neon = Neon::open(__DIR__ . '/data/products.neon');

$query = $neon->query();
$query->setGrouping(false)
    ->select('name, price')
    ->select('manufacturer')->as('brand')
    ->from('data.products')
    ->where('manufacturer', Operator::EQUAL, 'Manufacturer 3')
    ->or('name', Operator::EQUAL, 'Product 2')
    ->or('price', Operator::GREATER_THAN, 200);

dump($query->test());
dump(iterator_to_array($query->fetchAll()));

$query = $neon->query()
    ->select('totalCount')->as('totalPages')
    ->select('page')->as('actualPage')
    ->from('data.paginator');

dump($query->test());
dump($query->fetch());
