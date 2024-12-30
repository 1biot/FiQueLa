# UniQueL - Universal Query Language

> _[yu-nik-ju-el]_

![Packagist Version](https://img.shields.io/packagist/v/1biot/uniquel)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/uniquel/php)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/1biot/uniquel/ci.yml)
![Packagist Downloads](https://img.shields.io/packagist/dm/1biot/uniquel)
![Packagist License](https://img.shields.io/packagist/l/1biot/uniquel)

**U**ni**Q**ue**L** is a PHP library for seamless manipulation of data in
**JSON**, **YAML**, **NEON**, and **XML** formats. The library provides an SQL-inspired syntax for querying, filtering,
joining and aggregating data. It is designed for simplicity, modularity, and efficiency.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Getting Started](#getting-started)
    - [Supported Formats](#supported-formats)
    - [Basic Querying](#basic-querying)
    - [Fetching data](#fetching-data)
- [Advanced Usage](#advanced-usage)
    - [Sorting functions](#sorting-functions)
    - [Use HAVING](#use-having)
    - [Joining sources](#joining-sources)
    - [Aggregate Functions](#aggregate-functions)
    - [Pagination and Limit](#pagination-and-limit)
    - [SQL](#sql)
    - [Inspect queries](#inspect-queries)
- [Examples](#examples)
- [Contributions](#contributions)

## Features

- ✅ Support for **JSON**, **YAML**, **NEON**, and **XML** (easily extensible to other formats).
- ✅ SQL - Supports SQL string queries compatible with MySQL syntax.
- ✅ SQL-inspired capabilities:
    - **SELECT** for selecting fields and aliases.
    - **JOIN** for joining multiple data sources.
    - **WHERE** for filtering with various operators.
    - **ORDER BY** for sorting.
    - **HAVING** for filtering by aliases in queries.
    - **LIMIT** and **OFFSET** for pagination and result limits.
- ✅ Automatic conversion of queries into SQL-like syntax.
- 🚀 Unified API across all supported formats.
- [ ] **JSON Stream Parser**: Use a stream parser for large JSON files.
- [ ] **Support for CSV**: Enable querying data directly from CSV files.
- [ ] **Functions**: Add advanced functions such as `COALESCE`, `CONCAT`, `IF`, and more.
- [ ] **GROUP BY**: Introduce support for grouping data.
- [ ] **DELETE, UPDATE, INSERT**: Support for manipulating data in data sources
- [ ] **Documentation**: Create detailed guides and examples for advanced use cases.

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require 1biot/uniquel
```

## Getting Started

### Supported Formats

#### JSON
Native support for JSON data allows you to load it from files or strings:

```php
use UQL\Stream\Json;

// Load from a file
$json = Json::open('data.json');

// Or from a string
$json = Json::string(file_get_contents('data.json'));
```

#### YAML and NEON
To use YAML and NEON formats, you need to install the required libraries:

```bash
composer require symfony/yaml nette/neon
```

```php
use UQL\Stream\Yaml;
use UQL\Stream\Neon;

$yaml = Yaml::open('data.yaml');
$neon = Neon::open('data.neon');
```

#### XML
XML requires standard PHP extensions only:

```php
use UQL\Stream\Xml;

$xml = Xml::open('data.xml');
```

### Basic Querying

```php
use UQL\Enum\Operator;
use UQL\Enum\Sort;

$query = $json->query();

$results = $query
    ->select('name, age')
    ->from('users')
    ->where('age', Operator::GREATER_THAN, 18)
    ->orderBy('name')
    ->fetchAll();

foreach ($results as $user) {
    echo "{$user['name']} is {$user['age']} years old.\n";
}
```

### Fetching data

```php
use UQL\Enum\Operator;

$query = $yaml->query();

$query->fetchAll(); // to array
$query->fetch(); // first record
$query->fetchSingle('field'); // single field from first record
$query->fetchNth(4); // fetch 4th record
$query->fetchNth('even'); // fetch nth record with even index
$query->fetchNth('odd'); // fetch nth record with odd index
```

## Advanced Usage

### Sorting functions

```php
use UQL\Enum\Operator;
use UQL\Enum\Sort;

$results = $json->query()
    ->select('name, age')
    ->from('users')
    ->where('age', Operator::GREATER_THAN, 18)
    ->orderBy('name')->asc()
    ->orderBy('age')->desc()
    ->fetchAll();
```

### Use HAVING

This is useful when you want to filter by aliases in queries. Filtering not translate any nested values, but WHERE conditions does.

```php
use UQL\Enum\Operator;
use UQL\Enum\Sort;

$query = $json->query();

$results = $query
    ->select('name')->as('fullName')
    ->select('age')->as('years')
    ->from('users')
    ->having('years', Operator::GREATER_THAN, 18)
    ->orderBy('name')->asc()
    ->orderBy('age')->desc()
    ->fetchAll();

foreach ($results as $user) {
    echo "{$user['fullName']} is {$user['years']} years old.\n";
}
```

### Joining sources

Joining sources is possible with `leftJoin` and `innerJoin` methods. The following example demonstrates a
left join between **XML** and **JSON** file.

```php
use UQL\Enum\Operator;
use UQL\Helpers\Debugger;
use UQL\Stream\Json;
use UQL\Stream\Xml;

$usersFile = Json::open(__DIR__ . '/data/users.json');
$ordersFile = Xml::open(__DIR__ . '/data/orders.xml');

$orders = $ordersFile->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order');
    
Debugger::inspectQuery($orders);

$users = $usersFile->query()
    ->selectAll()
    ->from('data.users')
    ->leftJoin($orders, 'o')
        ->on('id', Operator::EQUAL, 'userId')
    ->where('o.totalPrice', Operator::EQUAL_STRICT, null);

Debugger::inspectQuery($users);
```

For results try the `composer example:join` command.

### Aggregate Functions

Aggregate functions runs `fetchAll()` internally.

```php
$totalAge = $query->sum('age');
$averageAge = $query->avg('age');
$count = $query->count();
```

### Pagination, Limit and Offset

```php
$results = $query
    ->select('name, age')
    ->from('users')
    ->page(2, 20);

// or using limit and offset
$results = $query
    ->select('name, age')
    ->from('users')
    ->limit(20)
    ->offset(40);
```

### SQL

#### Interpreted SQL

```php
$query->select('name, price')
    ->from('products')
    ->where('price', Operator::GREATER_THAN, 100)
    ->limit(10);

echo $query->test();
```

```sql
# Output:
SELECT
    name,
    price
FROM products
WHERE price > 100
LIMIT 10
```

#### Using SQL Strings

Parse SQL strings directly into queries for all supported formats. Now work in progress and supports only basics.

```xml
<?xml version="1.0" encoding="utf-8"?>
<root>
    <item id="1" category="A">
        <name>Item 1</name>
        <price>100</price>
        <brand>
            <code>BRAND-A</code>
            <name>Brand A</name>
        </brand>
    </item>
    <item id="2" category="B">
        <name>Item 2</name>
        <price>200</price>
        <brand>
            <code>BRAND-B</code>
            <name>Brand B</name>
        </brand>
    </item>
    <item id="3" category="C">
        <name>Item 3</name>
        <price>300</price>
        <brand>
            <code>BRAND-C</code>
            <name>Brand C</name>
        </brand>
    </item>
</root>
```

```php
$sql = <<<SQL
SELECT
    name, price
FROM root.item
WHERE
    brand.code == "BRAND-A"
    OR price > 200
ORDER BY name DESC
SQL;

Debugger::dump(iterator_to_array($xml->sql($sql)));
```

```
// Output:
array (2)
   0 => array (2)
   |  'name' => 'Item 3'
   |  'price' => '300'
   1 => array (2)
   |  'name' => 'Item 1'
   |  'price' => '100'
```

### Inspect queries

You can inspect your query for mor information about execution time, memory usage, SQL query and results.

```php
use UQL\Helpers\Debugger;
use UQL\Stream\Xml;

Debugger::start();

$ordersFile = Xml::open(__DIR__ . '/data/orders.xml');
$query = $ordersFile->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order');

Debugger::inspectQuery($query);

// or inspect query string which shows different between input SQL and applied SQL
Debugger::inspectQuerySql(
    $ordersFile,
    "SELECT id, user_id, total_price FROM orders.order"
);

Debugger::finish();
```

```
'------------------'
'### SQL query: ###'
'------------------'
>>> SELECT
>>> 	id AS orderId,
>>> 	user_id AS userId,
>>> 	total_price AS totalPrice
>>> FROM orders.order
'----------------'
'### Results: ###'
'----------------'
'### Count: 4'
'------------------'
'### First row: ###'
'------------------'
array (3)
   'orderId' => 1
   'userId' => 1
   'totalPrice' => 100

'------------------------------'
'Memory usage: 1.8518MB (emalloc)'
'Memory peak usage: 1.9661MB (emalloc)'
'Memory usage: 4MB (real)'
'Memory peak usage: 4MB (real)'
'------------------------------'
'Execution time (µs): 6276'
'Execution time (ms): 6.276'
'------------------------------'
'Final execution time (µs): 6296'
'Final execution time (ms): 6.296'
```

## Examples

Check the examples and run them using Composer. All examples uses `\UQL\Helpers\Debugger` and methods `inspectQuery` or
`inspectQuerySql` to show the results. More information about inspecting are [here](#inspect-queries).

```bash
composer examples
# or
composer example:join
composer example:neon
composer example:sql
composer example:test
composer example:xml
composer example:yaml
```

## Contributions

If you have suggestions or would like to contribute to these features, feel free to open an issue or a pull request!
