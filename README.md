# UniQueL - Universal Query Language 

> _[yu-nik-ju-el]_

![Packagist Version](https://img.shields.io/packagist/v/1biot/uniquel)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/1biot/uniquel/ci.yml)
![Packagist License](https://img.shields.io/packagist/l/1biot/uniquel)
![Packagist Downloads](https://img.shields.io/packagist/dm/1biot/uniquel)

![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/uniquel/php)
![Static Badge](https://img.shields.io/badge/PHPUnit-tests%3A_62-lightgreen)
![Static Badge](https://img.shields.io/badge/PHPUnit-asserts%3A_304-lightgreen)
![Static Badge](https://img.shields.io/badge/PHPStan-level:_6-8A2BE2)

**U**ni**Q**ue**L** is a PHP library for seamless manipulation of data in
**XML**, **CSV**, **JSON**, **YAML** and **NEON** formats. The library provides MySQL-inspired syntax for querying, filtering,
joining and aggregating data. It is designed for simplicity, modularity, and efficiency.

**Table of Contents:**

- 1 - [Features](#1-features)
- 2 - [Planning Features](#2-planning-features)
- 3 - [Installation](#3-installation)
- 4 - [Getting Started](#4-getting-started)
  - 4.1 - [Supported Formats](#41-supported-formats)
  - 4.2 - [Basic Querying](#42-basic-querying)
  - 4.3 - [Operators](#43-operators)
  - 4.4 - [Fetching Data](#44-fetching-data)
- 5 - [Advanced Usage](#5-advanced-usage)
  - 5.1 - [Sorting Functions](#51-sorting-functions)
  - 5.2 - [Use HAVING Conditions](#52-use-having-conditions)
  - 5.3 - [Joining Sources](#53-joining-sources)
  - 5.4 - [Functions](#54-functions)
  - 5.5 - [Mapping - Data Transfer Objects](#55-mapping---data-transfer-objects)
  - 5.6 - [Aggregate Functions](#56-aggregate-functions)
  - 5.7 - [Pagination, Limit and Offset](#57-pagination-limit-and-offset)
  - 5.8 - [SQL](#58-sql)
  - 5.9 - [Inspect Queries](#59-inspect-queries)
- 6 - [Examples](#6-examples)
- 7 - [Contributions](#7-contributions)

## 1. Features

- ✅ Support for **XML**, **CSV**, **JSON**, **YAML** and **NEON** (easily extensible to other formats).
- ✅ SQL-inspired capabilities:
  - **SELECT** for selecting fields and aliases.
  - **JOIN** for joining multiple data sources.
  - **WHERE** for filtering with various operators.
  - **ORDER BY** for sorting.
  - **HAVING** for filtering by aliases in queries.
  - **LIMIT** and **OFFSET** for pagination and result limits.
- ✅ Operators for filtering data:
  - **==**, **!==**, **=**, **!=**
  - **>**, **<**, **>=**, **<=**
  - and [more](#43-operators) ... 💪
- ✅ Data Transfer Objects (DTO)
- ✅ Advance selecting functions for nested data.
  - **COALESCE** for selecting first non-null value.
  - **CONCAT** for concatenating values.
  - **REVERSE** for reversing strings.
  - **UPPER** and **LOWER** for changing case.
  - **LENGTH** for getting length of string.
  - **ROUND**, **FLOOR** and **CEIL** for rounding numbers.
  - **MOD** for modulo operation.
  - **EXPLODE** for splitting values.
  - **IMPLODE** for joining values.
  - **MD5** and **SHA1** for hashing values.
  - more functions will arrive soon... 🚅
- 🚀 Unified API across all supported formats.
- ⚠️ SQL - Supports SQL string queries inspired with MySQL syntax. Syntax does not support yet all SQL fluent features.
- ⚠️ Automatic conversion of queries into SQL-like syntax. It is not fully compatible yet with SQL parser

## 2. Planning Features

- [ ] **JSON Stream Parser**: Use a stream parser for large JSON files.
- [ ] **Next file formats**: Add next file formats like [NDJson](https://github.com/ndjson/ndjson-spec) and [MessagePack](https://msgpack.org/)
- [ ] **Improve SQL parser**: SQL parser will be more complex. Will add support for direct selecting files like `FROM [csv:file.tmp]` or `JOIN([./subdir/file.json].data.users)`. It will bring support to all features from fluent **U**ni**Q**ue**L**.
- [ ] **GROUP BY**: Introduce support for grouping data.
- [ ] **DELETE, UPDATE, INSERT**: Support for manipulating data in files.
- [ ] **Documentation**: Create detailed guides and examples for advanced use cases.
- [ ] **Tests**: Increase test coverage.

## 3. Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require 1biot/uniquel
```

## 4. Getting Started

### 4.1. Supported Formats

#### XML

XML requires standard PHP extensions only (`libxml`, `simplexml` and `xmlreader`):

```php
use UQL\Stream\Xml;

$xml = Xml::open('data.xml');
$xml->setEncoding('windows-1250');
```

#### CSV

CSV requires `league/csv` package:

```bash
composer require league/csv
```

```php
use UQL\Stream\Csv;

$csv = Csv::open('data.xml')
    ->inputEncoding('windows-1250')
    ->setDelimiter(';')
    ->useHeader(true);
```

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

### 4.2. Basic Querying

```php
use UQL\Enum\Operator;
use UQL\Enum\Sort;

$query = $xml->query();

$results = $query
    ->select('name, age')
    ->from('users.user')
    ->where('age', Operator::GREATER_THAN, 18)
    ->orderBy('name')
    ->fetchAll();

foreach ($results as $user) {
    echo "{$user['name']} is {$user['age']} years old.\n";
}
```

### 4.3. Operators

#### EQUAL (STRICT)

```php
$query->where('age', Operator::EQUAL, 18);
$query->where('age', Operator::EQUAL, "18"); // same results

$query->where('age', Operator::EQUAL_STRICT, 18);
$query->where('age', Operator::EQUAL_STRICT, "18"); // it is not the same
```

#### NOT EQUAL (STRICT)

```php
$query->where('age', Operator::NOT_EQUAL, 18);
$query->where('age', Operator::NOT_EQUAL, "18"); // same results

$query->where('age', Operator::NOT_EQUAL_STRICT, 18);
$query->where('age', Operator::NOT_EQUAL_STRICT, "18"); // it is not the same
```

#### GREATER THAN (OR EQUAL)

```php
$query->where('age', Operator::GREATER_THAN, 18);
$query->where('age', Operator::GREATER_THAN_OR_EQUAL, 18);
```

#### LESS THAN (OR EQUAL)

```php
$query->where('age', Operator::LESS_THAN, 18);
$query->where('age', Operator::LESS_THAN_OR_EQUAL, 18);
```

#### (NOT) IN

```php
$query->where('age', Operator::IN, [18, 19, 20]);
$query->where('age', Operator::NOT_IN, [18, 19, 20]);
```

#### CONTAINS

```php
$query->where('name', Operator::CONTAINS, 'John');  // %John%
```

#### STARTS WITH

```php
$query->where('name', Operator::STARTS_WITH, 'John');  // John%
```

#### ENDS WITH

```php
$query->where('name', Operator::ENDS_WITH, 'John');  // %John
```

### 4.4. Fetching Data

```php
$query = $csv->query();

$query->fetchAll(); // to array
$query->fetch(); // first record
$query->fetchSingle('field'); // single field from first record
$query->fetchNth(4); // fetch 4th record
$query->fetchNth('even'); // fetch nth record with even index
$query->fetchNth('odd'); // fetch nth record with odd index
```

## 5. Advanced Usage

### 5.1. Sorting functions

```php
use UQL\Enum\Operator;

$results = $json->query()
    ->select('name, age')
    ->from('users')
    ->where('age', Operator::GREATER_THAN, 18)
    ->orderBy('name')->asc()
    ->orderBy('age')->desc()
    ->fetchAll();
```

### 5.2. Use HAVING Conditions

This is useful when you want to filter by aliases in queries. Filtering not translate any nested values, but WHERE conditions does.

```php
use UQL\Enum\Operator;

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

### 5.3. Joining Sources

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

### 5.4 Functions

Functions are useful for manipulating data in queries. You can use them in `select` method.

#### Hashing functions

**sha1**: SHA1 algorithm for hashing

```php
$query->sha1('name')
    ->as('hash'); // SELECT SHA1(name) AS hash
```

**md5**: MD5 algorithm for hashing

```php
$query->md5('name')
    ->as('hash'); // SELECT MD5(name) AS hash
```

#### Array functions

**explode**: split string to array

```php
$delimiter = '|';
$query->explode('Partner_Article', $delimiter)
    ->as('related') // SELECT EXPLODE("|", Partner_Article) AS related
```

Or alias to explode is `split()`. This example shows default delimiter `,`.

```php
$query->split('Partner_Article')
    ->as('related') // SELECT EXPLODE(",", Partner_Article) AS related
```

**implode**: join array to string

```php
$query->implode('categories[]->id', '|')
    ->as('catString'); // SELECT IMPLODE("|", categories[]->id) AS catString
```

Or alias to implode is `glue()`. This example shows default delimiter `,`.

```php
$query->glue('categories[]->id')
    ->as('catString'); // SELECT IMPLODE(",", categories[]->id) AS catString
```

#### String functions

**upper**: Convert string to upper case

```php
$query->upper('name')
    ->as('upperName') // SELECT UPPER(name) AS upperName
```

**lower**: Convert string to lower case

```php
$query->lower('name')->as('lowerName') // SELECT LOWER(name) AS lowerName
```

**length**: Get length of string

```php
$query->length('name')
    ->as('length') // SELECT LENGTH(name) AS length
```

**concat**: Concatenate values

```php
$query->concat('ArticleNr', 'CatalogNr')
    ->as('concatenateString'); // SELECT CONCAT(ArticleNr, CatalogNr) AS concatenateString
```

**concatWS**: Concatenate values with separator

```php
// Concatenate values with separator
$query->concatWS('/', 'ArticleNr', 'CatalogNr')
    ->as('concatenateString'); // SELECT CONCAT_WS("/", ArticleNr, CatalogNr) AS concatenateString
```

**coalesce**: Coalesce values (first non-null value)

```php
$query->coalesce('whatever', 'ArticleNr')
    ->as('coalesceString'); // SELECT COALESCE(ArticleNr, whatever) AS coalesceString
```

**coalesceNotEmpty**: Coalesce values when not empty (first non-empty value)

```php
$query->coalesceNotEmpty('whatever', 'ArticleNr')
    ->as('coalesceString'); // SELECT COALESCE_NE(whatever, ArticleNr) AS coalesceString
```

**reverse**: Reverse string

```php
$query->reverse('name')
    ->as('reversedName'); // SELECT REVERSE(name) AS reversedName
```

#### Numeric functions

**round**: Round number

```php
$query->round('price')
    ->as('roundedPrice') // SELECT ROUND(price, 0) AS roundedPrice
```

Or you can specify precision

```php
$query->round('price', 2)
    ->as('roundedPrice') // SELECT ROUND(price, 2) AS roundedPrice
```

**floor**: Round number down

```php
$query->floor('price')
    ->as('floorPrice') // SELECT FLOOR(price) AS floorPrice
```

**ceil**: Round number up

```php
$query->ceil('price')
    ->as('ceilPrice') // SELECT CEIL(price) AS ceilPrice
```

**mod**: Modulo operation, when divisor is zero then exception is thrown

```php
$query->modulo('price', 2)
    ->as('modPrice') // SELECT MOD(price, 2) AS modPrice
```

### 5.5. Mapping - Data Transfer Objects

You can map your results to Data Transfer Objects (**DTO**) with `$dto` property when using fetch functions.

Example with anonymous DTO object:

```php
use UQL\Enum\Operator;
use UQL\Helpers\Debugger;

$query = $this->json->query()
    ->select('id, name, price')
    ->select('brand.name')->as('brand')
    ->select('categories[]->name')->as('categories')
    ->select('categories[]->id')->as('categoryIds')
    ->from('data.products')
    ->where('price', Operator::GREATER_THAN, 200)
    ->orderBy('price')->desc()
    ->limit(1);

$dto = new class {
    public int $id;
    public string $name;
    public int $price;
    public string $brand;
    public array $categories;
    public array $categoryIds;
};

Debugger::dump($query->fetch($dto::class));
```

```
// Output:
class@anonymous #753
   id: 4
   name: 'Product D'
   price: 400
   brand: 'Brand B'
   categories: array (2)
   |  0 => 'Category D'
   |  1 => 'Category E'
   categoryIds: array (2)
   |  0 => 'CAT-D'
   |  1 => 'CAT-E'
```

You can use DTO classes as well:

```php
class ProductDto
{
    public int $id;
    public string $name;
    public int $price;
    public string $brand;
    public array $categories;
    public array $categoryIds;
}

class CategoryDto implements \Stringable
{
    public function __construct(
        public readonly string $name,
        public readonly string $id
    ) {}
    
    public function __toString() : string{
        return sprintf('%s-%s', $this->id, $this->name);  
    }
}

```

### 5.6. Aggregate Functions

Aggregate functions runs `fetchAll()` internally.

```php
$count = $query->count();
$minAge = $query->min('age');
$maxAge = $query->max('age');
$averageAge = $query->avg('age');
$totalPrice = $query->sum('price');
```

### 5.7. Pagination, Limit and Offset

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

### 5.8. SQL

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

### 5.9. Inspect Queries

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

Or inspect query string which shows different between input SQL and applied SQL

```php
Debugger::inspectQuerySql(
    $ordersFile,
    "SELECT id, user_id, total_price FROM orders.order"
);
```

```
'---------------------------'
'### Original SQL query: ###'
'---------------------------'
>>> SELECT id, user_id, total_price FROM orders.order
'------------------'
'### SQL query: ###'
'------------------'
...
```

## 6. Examples

Check the examples and run them using Composer. All examples uses `\UQL\Helpers\Debugger` and methods `inspectQuery` or
`inspectQuerySql` to show the results. More information about inspecting are [here](#59-inspect-queries).

```bash
composer examples
# or
composer example:csv
composer example:join
composer example:neon
composer example:sql
composer example:test
composer example:xml
composer example:yaml
```

## 7. Contributions

If you have suggestions or would like to contribute to these features, feel free to open an issue or a pull request!

**How to contribute:**
- Fork the repository
- Create a new branch
- Make your changes
- Create a pull request
- All tests must pass
- Wait for approval
- 🚀
