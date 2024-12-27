<?php

use UQL\Enum\Operator;
use UQL\Stream\Neon;

require __DIR__ . '/../vendor/autoload.php';

$neon = Neon::open(__DIR__ . '/data/products.neon');

$query = $neon->query();
$query->select('name, price')
    ->select('manufacturer')->as('brand')
    ->from('data.products')
    ->where('manufacturer', Operator::EQUAL, 'Manufacturer 3')
    ->or('name', Operator::EQUAL, 'Product 2')
    ->or('price', Operator::GREATER_THAN, 200);

dump($query->test());
// Output:
// 'SELECT \n
// \t    name,\n
// \t    price,\n
// \t    manufacturer AS brand\n
// FROM data.products\n
// WHERE\n
// \t    manufacturer = 'Manufacturer 3' \n
// \t    OR name = 'Product 2' \n
// \t    OR price > 200'

dump(iterator_to_array($query->fetchAll()));
// Output:
// array (2)
//   0 => array (3)
//   |  'name' => 'Product 2'
//   |  'price' => 200
//   |  'brand' => 'Manufacturer 2'
//   1 => array (3)
//   |  'name' => 'Product 3'
//   |  'price' => 300
//   |  'brand' => 'Manufacturer 3'

$query = $neon->query()
    ->select('totalCount')->as('totalPages')
    ->select('page')->as('actualPage')
    ->from('data.paginator');

dump($query->test());
// Output:
// 'SELECT \n
// \t    totalCount AS totalPages,\n
// \t    page AS actualPage\n
// FROM data.paginator'

dump($query->fetch());
// Output:
// array (2)
//   'totalPages' => 3
//   'actualPage' => 1
