# UniQueL - Universal Query Language 

> _[yu-nik-ju-el]_

![Packagist Version](https://img.shields.io/packagist/v/1biot/uniquel)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/1biot/uniquel/ci.yml)
![Packagist License](https://img.shields.io/packagist/l/1biot/uniquel)
![Packagist Downloads](https://img.shields.io/packagist/dm/1biot/uniquel)

![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/uniquel/php)
![Static Badge](https://img.shields.io/badge/PHPUnit-tests%3A_91-lightgreen)
![Static Badge](https://img.shields.io/badge/PHPUnit-asserts%3A_366-lightgreen)
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
    - 4.4.1 - [Getting and Fetching Data](#441-getting-and-fetching-data)
    - 4.4.2 - [Proxy Data](#442-stream-data)
    - 4.4.3 - [Proxy Data](#442-proxy-data)
- 5 - [Advanced Usage](#5-advanced-usage)
  - 5.1 - [Sorting Functions](#51-sorting-functions)
  - 5.2 - [Use HAVING Conditions](#52-use-having-conditions)
  - 5.3 - [Joining Sources](#53-joining-sources)
  - 5.4 - [Functions](#54-functions)
    - 5.4.1 - [Hashing Functions](#541-hashing-functions)
    - 5.4.2 - [Array Functions](#542-array-functions)
    - 5.4.3 - [String Functions](#543-string-functions)
    - 5.4.4 - [Numeric Functions](#544-numeric-functions)
    - 5.4.5 - [Aggregate Functions](#545-aggregate-functions)
  - 5.5 - [Mapping - Data Transfer Objects](#55-mapping---data-transfer-objects)
  - 5.6 - [Pagination, Limit and Offset](#56-pagination-limit-and-offset)
  - 5.7 - [SQL](#57-sql)
    - 5.7.1 - [Interpreted SQL](#571-interpreted-sql)
    - 5.7.2 - [Using SQL Strings](#572-using-sql-strings)
  - 5.8 - [Inspect Queries and Benchmarking](#58-inspect-queries-and-benchmarking)
    - 5.8.1 - [Inspect Queries](#581-inspect-queries) 
    - 5.8.2 - [Benchmarking](#582-benchmarking)
- 6 - [Examples](#6-examples)
- 7 - [Contributions](#7-contributions)

## 1. Features

- ✅ Support for **XML**, **CSV**, **JSON**, **YAML** and **NEON** (easily extensible to other formats).
- ✅ Support stream generator for large **JSON**, **XML** and **CSV** files.
- ✅ SQL-inspired capabilities:
  - **SELECT** for selecting fields and aliases.
  - **JOIN** for joining multiple data sources.
  - **WHERE** for filtering with various operators.
  - **HAVING** for filtering by aliases in queries.
  - **GROUP BY** for grouping data.
  - **ORDER BY** for sorting.
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
- ⚠️ Functions `JOIN`, `ORDER BY` and `GROUP BY` are not memory efficient, because joining data or sorting data requires to load all data into memory. But everything else is like ⚡️.
- ⚠️ SQL - Supports SQL string queries inspired with MySQL syntax. Syntax does not support yet all SQL fluent features.
- ⚠️ Automatic conversion of queries into SQL-like syntax. It is not fully compatible yet with SQL parser

## 2. Planning Features

- [ ] **Next file formats**: Add next file formats like [NDJson](https://github.com/ndjson/ndjson-spec) and [MessagePack](https://msgpack.org/)
- [ ] **Improve SQL parser**: SQL parser will be more complex. Will add support for direct selecting files like `FROM [csv:file.tmp]` or `JOIN([./subdir/file.json].data.users)`. It will bring support to all features from fluent **U**ni**Q**ue**L**.
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

Native support for JSON data allows you to load it from files or strings. Uses `json_decode` function and for large
files use [JSON Stream](#json-stream).

```php
use UQL\Stream\Json;

// Load from a file
$json = Json::open('data.json');

// Or from a string
$json = Json::string(file_get_contents('data.json'));
```

#### JSON Stream

JSON Stream is useful for large JSON files. It uses `halaxa/json-machine` package:

```bash
composer require halaxa/json-machine
```

```php
use UQL\Stream\JsonStream;

$json = JsonStream::open('big/data.json');
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

$query = $xml->query()
    ->select('name, age')
    ->from('users.user')
    ->where('age', Operator::GREATER_THAN, 18)
    ->orderBy('name')->asc();

$results = $query->execute();
foreach ($results->fetchAll() as $user) {
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

### 4.4. Getting and Fetching Data

For results from `Query` use method `execute()`. It returns `UQL\Results\ResultsProvider` object which can be used for
fetching data.

```php
// create a query like $query->select('field')->from('path) ...
$query = $csv->query();
// get the results
$results = $query->execute();
```

**getIterator():**`\Traversable`

Has a same behavior as `fetchAll()` method without $dto parameter.
    
```php
$results = $query->execute();
foreach ($results->getIterator() as $user) {
    echo "{$user['name']} is {$user['age']} years old.\n";
}
```

**fetchAll(**_class-string_ `$dto = null`**):**`\Generator`

Method to fetch all records from the results. It returns a generator that can be used to iterate over the results and
applies a DTO if needed.

```php 
foreach ($results->fetchAll() as $user) {
    echo "{$user['name']} is {$user['age']} years old.\n";
}
```

**fetch(**_class-string_ `$dto = null`**):**`?mixed`

Method to fetch first record and applies a DTO if needed.

```php
$user = $results->fetch();
```

**fetchSingle(**_string_`$field`**):**`mixed`

Method to fetch single field from first record.

```php
$name = $results->fetchSingle('name');
```

**fetchNth(**_int_ | _string_`$n`**,** _class-string_ `$dto = null`**):**`\Generator`

When `$n` is an integer then it fetches every `nth` record. When `$n` is string (`even` or `odd`) then it fetches every even or odd record and applies a DTO if needed.

```php
$fourthUsers = $results->fetchNth(4);
// or
$evenUsers = $results->fetchNth('even');
$oddUsers = $results->fetchNth('odd');
```

**exists():**`bool`

Method to check if any record exists. It tries to fetch first record and return true if it exists.

```php
if ($results->exists()) {
    echo "There are some records.\n";
}
```

**count():**`int`

Method to count records.

```php
$count = $results->count();
```

**sum(**_string_`$field`**):**`float`

Method to sum values by field.

```php
$sum = $results->sum('total_price');
```

**avg(**_string_`$field`**,** _int_`$decimalPlaces = 2` **):**`float`

Method to calculate average value by field. You can specify a number of decimal places.

```php
$avg = $results->avg('total_price', 2);
```

**max(**_string_`$field`**):**`float`

Method to get maximum value.

```php
$max = $results->max('total_price);
```

**min(**_string_`$field`**):**`float`

Method to get minimum value.

```php
$min = $results->min('total_price');
```

#### 4.4.1 Stream Data

The advantage of `UQL\Results\Stream` is that it reads data directly from the file with each operation, ensuring it is
always up-to-date. This is memory efficient, but it is slower for too many iterations. About 2.01s for 50 000 iterations.

#### 4.4.2 Proxy Data

Object `UQL\Results\Stream` could provide proxy data for fetching results by `getProxy()` method. This is not so memory
efficient, but it is faster, much faster. About 0.003s for 50 000 iterations to compare 2.01s from `Stream` object.

```php
// create a query like $query->select('field')->from('path') ...
$query = $csv->query();
// get the results
$proxyResults = $query->execute()->getProxy();
// and then fetch data
$proxyResults->fetchAll();
$proxyResults->fetch();
...
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
    ->execute()
    ->fetchAll();
```

### 5.2. Use HAVING Conditions

This is useful when you want to filter by aliases in queries. Filtering not translate any nested values, but WHERE conditions does.

```php
use UQL\Enum\Operator;

$query = $json->query();

$results = $query
    ->select('name')
    ->round('price', 0)->as('roundedPrice')
    ->from('products')
    ->where('currency.code', Operator::EQUAL_STRICT, 'USD')
    ->having('roundedPrice', Operator::GREATER_THAN, 125)
    ->execute()
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

Functions are useful for manipulating data in queries. You can use them in `select` method. Functions values may be sets
as aliases which can be chained with previously called functions.

```php
// try to get sell price from actionPrice or price and round it to 2 decimal places
$json->query()
    ->coalesce('actionPrice', 'price')->as('sellPrice') 
    ->round('sellPrice', 2)->as('roundedPrice');
```

#### 5.4.1. Hashing functions

**sha1(**_string_`$field`**):**`Query`

SHA1 algorithm for hashing

```php
$query->sha1('name')
    ->as('hash'); // SELECT SHA1(name) AS hash
```

**md5(**_string_`$field`**):**`Query`

MD5 algorithm for hashing

```php
$query->md5('name')
    ->as('hash'); // SELECT MD5(name) AS hash
```

#### 5.4.2. Array functions

**explode(**_string_`$field`**,** _string_`$delimiter = ","`**):**`Query`

**split(**_string_`$field`**,** _string_`$delimiter = ","`**):**`Query`

split string to array

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

**implode(**_string_`$field`**,** _string_`$delimiter = ","`**):**`Query`

**glue(**_string_`$field`**,** _string_`$delimiter = ","`**):**`Query`

Join array to string

```php
$query->implode('categories[]->id', '|')
    ->as('catString'); // SELECT IMPLODE("|", categories[]->id) AS catString
```

Or alias to implode is `glue()`. This example shows default delimiter `,`.

```php
$query->glue('categories[]->id')
    ->as('catString'); // SELECT IMPLODE(",", categories[]->id) AS catString
```

#### 5.4.3. String functions

**upper(**_string_`$field`**):**`Query`

Convert string to upper case

```php
$query->upper('name')
    ->as('upperName') // SELECT UPPER(name) AS upperName
```

**lower(**_string_`$field`**):**`Query`

Convert string to lower case

```php
$query->lower('name')->as('lowerName') // SELECT LOWER(name) AS lowerName
```

**length(**_string_`$field`**):**`Query`

Get length of string

```php
$query->length('name')
    ->as('length') // SELECT LENGTH(name) AS length
```

**concat(**_string_`...$fields`**):**`Query`

Concatenate values with no separator.

```php
$query->concat('ArticleNr', 'CatalogNr')
    ->as('concatenateString'); // SELECT CONCAT(ArticleNr, CatalogNr) AS concatenateString
```

**concatWS(**_string_`$separator`**,** _string_`...$fields`**):**`Query`

Concatenate values with separator

```php
// Concatenate values with separator
$query->concatWS('/', 'ArticleNr', 'CatalogNr')
    ->as('concatenateString'); // SELECT CONCAT_WS("/", ArticleNr, CatalogNr) AS concatenateString
```


**coalesce(**_string_`...$fields`**)**`: Query`

Coalesce values (first non-null value)

```php
$query->coalesce('whatever', 'ArticleNr')
    ->as('coalesceString'); // SELECT COALESCE(ArticleNr, whatever) AS coalesceString
```

**coalesceNotEmpty(**_string_`...$fields`**)**`: Query`

Coalesce values when not empty (first non-empty value)

```php
$query->coalesceNotEmpty('whatever', 'ArticleNr')
    ->as('coalesceString'); // SELECT COALESCE_NE(whatever, ArticleNr) AS coalesceString
```

**reverse(**_string_`$field`**):**`Query`

Reverse string

```php
$query->reverse('name')
    ->as('reversedName'); // SELECT REVERSE(name) AS reversedName
```

#### 5.4.4. Numeric functions

**round(**_string_`$field`**,** _int_`$precision = 0`**):**`Query`

Round number mathematically

```php
$query->round('price')
    ->as('roundedPrice') // SELECT ROUND(price, 0) AS roundedPrice
```

Or you can specify precision

```php
$query->round('price', 2)
    ->as('roundedPrice') // SELECT ROUND(price, 2) AS roundedPrice
```

**floor(**_string_`$field`**):**`Query`

Round number down

```php
$query->floor('price')
    ->as('floorPrice') // SELECT FLOOR(price) AS floorPrice
```

**ceil(**_string_`$field`**):**`Query`

Round number up

```php
$query->ceil('price')
    ->as('ceilPrice') // SELECT CEIL(price) AS ceilPrice
```

**modulo(**_string_`$field`**,** _int_`$divisor`**):**`Query`

Modulo operation, when divisor is zero then exception is thrown

```php
$query->modulo('price', 2)
    ->as('modPrice') // SELECT MOD(price, 2) AS modPrice
```

#### 5.4.5. Aggregate Functions

Like in SQL, you can use these functions to aggregate data.

**count(**_string_`$field = null`**):**`Query`

Count number of rows. When `$field` at `$row` does not exist then it counts only rows with non-null values.

```php
$query->count('id')
    ->as('count'); // SELECT COUNT(id) AS count
// or
$query->count()
    ->as('count'); // SELECT COUNT(*) AS count
```

**sum(**_string_`$field`**):**`Query`

Select sum of values

```php
$query->sum('price')
    ->as('sum'); // SELECT SUM(price) AS sum
```

**group_concat(**_string_`$field`**,** _string_`$separator = ","`**):**`Query`

Concatenate values

```php
$query->groupConcat('name')
    ->as('concatenatedNames'); // SELECT GROUP_CONCAT(name, ",") AS concatenatedNames
// or with separator
$query->groupConcat('name', '|')
    ->as('concatenatedNames'); // SELECT GROUP_CONCAT(name, "|") AS concatenatedNames
```

**avg(**_string_`$field`**):**`Query`

Select average value of column

```php
$query->avg('price')
    ->as('avg'); // SELECT AVG(price) AS avg
```

**min(**_string_`$field`**):**`Query`

Select minimum value

```php
$query->min('price')
    ->as('min'); // SELECT MIN(price) AS min
```

**max(**_string_`$field`**):**`Query`

Select maximum value

```php
$query->max('price')
    ->as('max'); // SELECT MAX(price) AS max
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

Debugger::dump($query->execute()->fetch($dto::class));
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

### 5.6. Pagination, Limit and Offset

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

### 5.7. SQL

#### 5.7.1. Interpreted SQL

All fluent queries can be interpreted to SQL strings. This is useful for debugging and understanding how queries are
executed.

```php
$query = JsonStream::open(__DIR__ . '/data/products.tmp')->query();
$query->selectAll()
    ->select('brand.code')->as('brandCode')
    ->groupConcat('id', '/')->as('products')
    ->groupSum('price')->as('totalPrice')
    ->groupCount('productId')->as('productCount')
    ->from('data.products')
    ->where('price', Operator::LESS_THAN, 300)
    ->or('price', Operator::GREATER_THAN, 400)
    ->groupBy('brandCode')
    ->orderBy('productCount')->desc();

echo $query->test();
```

Output is referenced to SQL query which will be compatible with future version of SQL parser.

```sql
SELECT
  brand.code AS brandCode,
  GROUP_CONCAT(id, "/") AS products,
  SUM(price) AS totalPrice,
  COUNT(productId) AS productCount
FROM [json://products.tmp].data.products
WHERE
  price < 200
  OR price > 300
GROUP BY brandCode
ORDER BY productCount DESC
```

#### 5.7.2. Using SQL Strings

> ⚠️ Parser does not support yet `functions`, `joins`, `group by`, `having` conditions and `from` abilities.
> Parser will be more complex in the future, and now it is for testing purposes.

Parse SQL strings directly into queries for all supported file formats. Now work in progress and supports only basics
functions. Idea is to use SQL strings for creating queries without fluent syntax. Now it could be used only for simple queries directly to files.

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
    name AS productName,
    price
FROM root.item
WHERE
    brand.code == "BRAND-A"
    OR price > 200
ORDER BY productName DESC
SQL;

$results = $xml->sql($sql);
Debugger::dump(iterator_to_array($results->fetchAll()));
```

```
// Output:
array (2)
   0 => array (2)
   |  'productName' => 'Item 3'
   |  'price' => '300'
   1 => array (2)
   |  'productName' => 'Item 1'
   |  'price' => '100'
```

In future will be possible to use `from` and `join` to directly loadings data from files and fluent interface will be
fully compatible with SQL strings.

### 5.8. Inspect Queries and Benchmarking

### 5.8.1. Inspect Queries
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

Debugger::end();
```

Or inspect query string which shows different between input SQL and applied SQL

```php
Debugger::inspectQuerySql(
    $ordersFile,
    "SELECT id, user_id, total_price FROM orders.order"
);
```

### 5.8.2. Benchmarking

You can benchmark your queries and test their performance through the number of iterations.

```php
use UQL\Helpers\Debugger;
use UQL\Stream\Xml;

$query = Xml::open(__DIR__ . '/data/orders.xml')->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order')

Debugger::start();
Debugger::benchmarkQuery($query, 1000);
```

For more information about inspecting queries and benchmarking, see the [examples](#6-examples)

## 6. Examples

Check the examples and run them using Composer. All examples uses `\UQL\Helpers\Debugger` and methods `inspectQuery` or
`inspectQuerySql` or `benchmarkQuery` to show the results.

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

Runs `composer example:join` and output will look like this:

```
'=================='
'*** SQL query: ***'
'=================='
>> SELECT 
>>   id AS orderId,
>>   user_id AS userId,
>>   total_price AS totalPrice
>> FROM [orders.xml].orders.order
'================'
'*** Results: ***'
'================'
'> Count: 4'
'=================='
'*** First row: ***'
'=================='
array (3)
   'orderId' => 1
   'userId' => 1
   'totalPrice' => 100

'------------------------------'
'> Memory usage: 2.0085MB (emalloc)'
'> Memory peak usage: 2.0725MB (emalloc)'
'------------------------------'
'> Execution time (s): 0.006779'
'> Execution time (ms): 6.779'
'> Execution time (µs): 6779'
'=================='
'*** SQL query: ***'
'=================='
>> SELECT 
>>   id,
>>   name,
>>   o.orderId AS orderId,
>>   o.totalPrice AS totalPrice
>> FROM [json://memory].data.users
>> LEFT JOIN 
>> (
>>   SELECT 
>>     id AS orderId,
>>     user_id AS userId,
>>     total_price AS totalPrice
>>   FROM [orders.xml].orders.order
>> ) AS o ON id = userId
>> GROUP BY orderId
>> ORDER BY totalPrice DESC
'================'
'*** Results: ***'
'================'
'> Count: 5'
'=================='
'*** First row: ***'
'=================='
array (4)
   'id' => 2
   'name' => 'John Doe 2'
   'orderId' => 3
   'totalPrice' => 600

'------------------------------'
'> Memory usage: 2.0278MB (emalloc)'
'> Memory peak usage: 2.0725MB (emalloc)'
'------------------------------'
'> Execution time (s): 0.00233'
'> Execution time (ms): 2.33'
'> Execution time (µs): 2330'
'========================'
'*** Benchmark Query: ***'
'========================'
'> 10 000 iterations'
'=================='
'*** SQL query: ***'
'=================='
>> SELECT 
>>   id,
>>   name,
>>   o.orderId AS orderId,
>>   o.totalPrice AS totalPrice
>> FROM [json://memory].data.users
>> LEFT JOIN 
>> (
>>   SELECT 
>>     id AS orderId,
>>     user_id AS userId,
>>     total_price AS totalPrice
>>   FROM [orders.xml].orders.order
>> ) AS o ON id = userId
>> GROUP BY orderId
>> ORDER BY totalPrice DESC
'========================='
'*** STREAM BENCHMARK: ***'
'========================='
'> Size (KB): 2.63'
'> Count: 5'
'> Iterated results: 50 000'
'------------------------------'
'> Memory usage: 2.0279MB (emalloc)'
'> Memory peak usage: 2.0725MB (emalloc)'
'------------------------------'
'> Execution time (s): 2.016064'
'> Execution time (ms): 2016.064'
'> Execution time (µs): 2016064'
'========================'
'*** PROXY BENCHMARK: ***'
'========================'
'> Size (KB): 0.58'
'> Count: 5'
'> Iterated results: 50 000'
'------------------------------'
'> Memory usage: 2.0299MB (emalloc)'
'> Memory peak usage: 2.0725MB (emalloc)'
'------------------------------'
'> Execution time (s): 0.003376'
'> Execution time (ms): 3.376'
'> Execution time (µs): 3376'
'=============================='
'>> Final execution time (s): 2.028676'
'>> Final execution time (ms): 2028.676'
'>> Final execution time (µs): 2028676'
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
