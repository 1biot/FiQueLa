<?php

use JQL\Enum\Operator;
use JQL\Stream\Yaml;

require __DIR__ . '/../vendor/autoload.php';

$yaml = Yaml::open(__DIR__ . '/data/products.yaml');

$query = $yaml->query();
$query->setGrouping(false)
    ->select('name, price')
    ->select('brand.name')->as('brand')
    ->from('data.products')
    ->where('brand.code', Operator::EQUAL, 'BRAND-A')
    ->or('name', Operator::EQUAL, 'Product 2')
    ->or('price', Operator::GREATER_THAN, 200);

dump($query->test());
dump(iterator_to_array($query->fetchAll()));

$query = $yaml->query()
    ->select('totalCount')->as('totalPages')
    ->select('page')->as('actualPage')
    ->from('data.paginator');

dump($query->test());
dump($query->fetch());
