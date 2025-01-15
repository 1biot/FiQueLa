# FiQueLa: File Query Language 

> _[fi-kju-ela]_

![Packagist Version](https://img.shields.io/packagist/v/1biot/uniquel)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/1biot/uniquel/ci.yml)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/uniquel/php)
![Static Badge](https://img.shields.io/badge/PHPUnit-tests%3A_115-lightgreen)
![Static Badge](https://img.shields.io/badge/PHPUnit-asserts%3A_409-lightgreen)
![Static Badge](https://img.shields.io/badge/PHPStan-level:_6-8A2BE2)

![Packagist License](https://img.shields.io/packagist/l/1biot/uniquel)
![Packagist Downloads](https://img.shields.io/packagist/dm/1biot/uniquel)

**F**i**Q**ue**L**a is a powerful PHP library that brings SQL-inspired querying capabilities to structured data formats
like **XML**, **CSV**, **JSON**, **YAML** and **NEON**. Designed for simplicity and modularity, it allows you to filter,
join, and aggregate data with a familiar and efficient syntax. Whether you're working with large datasets or integrating
various sources, **F**i**Q**ue**L**a provides a seamless way to manipulate and explore your data.

**Features**:

- 📂 **Supports multiple formats**: Work seamlessly with XML, CSV, JSON, YAML, and NEON.
- 🛠️ **SQL-inspired syntax**: Perform `SELECT`, `JOIN`, `WHERE`, `GROUP BY`, `ORDER BY` and more.
- ✍️ **Flexible Querying**: Write SQL-like strings or use the fluent API for maximum flexibility.
- 📊 **Advanced functions**: Access features like `SUM`, `COUNT`, `AVG`, `GROUP_CONCAT`, `MD5`, `UPPER`, and many more.
- 🚀 **Efficient with Large Files**: Optimized for processing JSON, XML, and CSV files with tens of thousands of rows using stream processing.
- 🧑‍💻 **Developer-Friendly**: Map results to DTOs for easier data manipulation.
- ⭐ **Unified API across all supported formats**: Use a consistent API for all your data needs.

**Table of Contents**:

- _I_ - [Overview](#i-overview)
- _II_ - [Installation](#ii-installation)
- _III_ - [Getting Started](#iii-getting-started)
  - _III.A_ - [Supported Formats](#iiia-supported-formats)
  - _III.B_ - [Basic Quering](#iiib-basic-querying)
  - _III.C_ - [Fetching Data](#iiic-fetching-data)
  - _III.D_ - [Mapping - Data Transfer Objects](#iiid-mapping---data-transfer-objects)
- _IV_ - [Query Life Cycle](#iv-query-life-cycle)
- _V_ - [Advance Features](#v-advance-features)
  - _V.A_ - [Joining Data Sources](#va-joining-data-sources)
  - _V.B_ - [Aggregations and Functions](#vb-aggregations-and-functions)
  - _V.C_ - [Sorting and Filtering](#vc-sorting-and-filtering)
  - _V.D_ - [Pagination and Limits](#vd-pagination-and-limits)
  - _V.E_ - [SQL Integration](#ve-sql-integration)
  - _V.F_ - [Query Inspection and Benchmarking](#vf-query-inspection-and-benchmarking)
- _VI_ - [Examples](#vi-examples)
- _VII_ - [Knowing issues](#vii-knowing-issues)
- _VIII_ - [Planning Features](#viii-planning-features)
- _IX_ - [Contributions](#ix-contributions)

## I. Overview

Why limit SQL to databases when it can be just as effective for querying structured data? **F**i**Q**ue**L**a (File Query Language)
brings the power of SQL to your files. Whether you're working with **JSON**, **XML**, **CSV**, or **YAML**, **F**i**Q**ue**L**a enables you to interact with these formats using familiar SQL syntax.

Key highlights:
- **Universal Querying**: Use SQL-like queries to filter, sort, join, and aggregate data across multiple file types.
- **Data Formats Support**: Seamlessly work with JSON, XML, CSV, YAML, and more.
- **Powerful Features**: Access advanced SQL features like `GROUP BY`, `HAVING`, and functions for data transformation directly on your file-based datasets.
- **Developer-Friendly**: Whether you're a beginner or an experienced developer, FiQueLa offers a simple and consistent API for all your data needs.
- **Flexible Integration**: Ideal for scenarios where data lives in files rather than traditional databases.
- **SQL-Like Strings**: Write and execute SQL-like string queries directly, providing an alternative to fluent syntax for greater flexibility and familiarity.

Use **F**i**Q**ue**L**a to:
- Simplify data extraction and analysis from structured files.
- Combine data from multiple sources with ease.
- Create lightweight data processing pipelines without a full-fledged database.

**F**i**Q**ue**L**a empowers developers to unlock the potential of file-based data with the familiar and expressive language of SQL.

## II. Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require 1biot/fiquela
```

Install packages for optional features:

```bash
composer require league/csv halaxa/json-machine symfony/yaml nette/neon tracy/tracy
```

## III. Getting Started

### III.A. Supported Formats

#### CSV

**F**i**Q**ue**L**a using internally `league/csv` package for CSV files. This support is optional and if you want to use it, you need to install it:

```bash
composer require league/csv
```

And then you can use it like this:

```php
use FQL\Stream\Csv;

$csv = Csv::open('data.csv')
    ->inputEncoding('windows-1250')
    ->setDelimiter(';')
    ->useHeader(true);
```

> ⚠️ **Note**: **FQL** currently supports CSV files only, with no support for CSV strings yet. 

#### JSON

Native support for JSON data allows you to load it from files or strings. Uses `json_decode` function and for large
files is recommended to use [JSON Stream](#json-stream).

```php
use FQL\Stream\Json;

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

And then you can use it like this:

```php
use FQL\Stream\JsonStream;

$json = JsonStream::open('big/data.json');
```

#### XML

XML support requires only standard PHP extensions (`libxml`, `simplexml` and `xmlreader`):

```php
use FQL\Stream\Xml;

$xml = Xml::open('data.xml');
$xml->setEncoding('windows-1250');
```

#### YAML and NEON

To use YAML and NEON formats are optional too, and you need to install the required libraries:

```bash
composer require symfony/yaml nette/neon
```

```php
use FQL\Stream\Yaml;
use FQL\Stream\Neon;

$yaml = Yaml::open('data.yaml');
$neon = Neon::open('data.neon');
```

### III.B. Basic Querying

It is example how you can easily query data from files. 

```php
use FQL\Enum\Operator;

$scannedIdFromRFID = '1234567890';

// creating query
$query = $xml->query()
    ->select('name, age')
    ->from('users.user')
    ->where('age', Operator::GREATER_THAN_OR_EQUAL, 18)
    ->and('id', Operator::EQUAL_STRICT, $scannedIdFromRFID);
```

### III.C. Fetching Data

Executing a query does not immediately execute it; instead, it prepares the query for lazy loading. The `execute()` method
simply returns an `IteratorAggregate`, leaving it up to you to decide how to process the results.

`execute()` method returns the `FQL\Results\ResultsProvider` object, which can be used to fetch data. This method accepts
a parameter to specify the fetching mode—either `Results\Stream` or `Results\InMemory`. By default, the parameter is `null`,
and the library will automatically select the most suitable option it is affected by sql itself. 

```php
use FQL\Results;

// get the results
$results = $query->execute();
$results = $query->execute(Results\InMemory::class);
$results = $query->execute(Results\Stream::class);
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

### III.D. Mapping - Data Transfer Objects

You can map your results to Data Transfer Objects (**DTO**) with `$dto` property when using fetch functions.

Example with anonymous DTO object:

```php
use FQL\Enum\Operator;
use FQL\Query\Debugger;

$query = $this->json->query()
    ->select('id, name, price')
    ->select('brand.name')->as('brand')
    ->select('categories[]->name')->as('categories')
    ->select('categories[]->id')->as('categoryIds')
    ->from('data.products')
    ->where('price', Operator::GREATER_THAN, 200)
    ->orderBy('price')->desc();

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

Output will look like this:

```
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

You can use standard classes as DTO as well:

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
    ) {
    }
    
    public function __toString() : string
    {
        return sprintf('%s-%s', $this->id, $this->name);  
    }
}

```

### IV. Query Life Cycle

1) **FROM** and **JOIN**:
    - Data are loaded from the sources specified in the `FROM` clause.
    - If `JOIN` is present, data is combined based on the join conditions.
2) **WHERE**:
    - Filters rows based on conditions specified in the `WHERE` clause.
    - Filtering happens at the raw data level before any grouping or calculations.
3) **DISTINCT**:
    - If the `DISTINCT` clause is used, duplicate rows are removed based on the specified columns.
    - `DISTINCT` is skipped if a `GROUP BY` clause is present, as grouping inherently eliminates duplicates.
4) **GROUP BY**:
    - Data is grouped based on the columns specified in the `GROUP BY` clause.
    - This prepares grouped data for aggregate functions like `SUM`, `COUNT`, `AVG`, etc.
5) **SELECT**:
    - Columns, expressions, and aggregate functions defined in the `SELECT` clause are processed.
    - The output includes either aggregated data or individual columns.
6) **HAVING**:
    - Filters the results produced by the `SELECT` clause.
    - Primarily used to filter grouped data after aggregation.
7) **ORDER BY**:
    - Results are sorted based on the specified columns or expressions.
    - Sorting can be in ascending (`ASC`), descending (`DESC`) or shuffled (`SHUFFLE`) and naturally sorted (`NATSORT`) order.
8) **LIMIT** and **OFFSET**:
    - Limits the number of rows returned by the query using `LIMIT`.
    - If `OFFSET` is present, it skips a specified number of rows before returning the results.

___

**Example Query Execution**:

For the query:

```sql
SELECT name, SUM(sales) AS total_sales
FROM [employees.xml].employees.employee
WHERE age > 30
GROUP BY name
HAVING total_sales > 1000
ORDER BY total_sales DESC
LIMIT 10;
```

**Execution Order**:
1) **FROM** and **JOIN**:
   - Data is loaded from the employees file `employees.xml`.
2) **WHERE**:
   - Filters rows where `age > 30`.
3) **GROUP BY**:
   - Groups data by the `name` column.
4) **SELECT**:
   - Processes grouped data and calculates `SUM(sales)` for each group.
5) **HAVING**:
   - Filters groups where `total_sales > 1000`.
6) **ORDER BY**:
   - Sorts results by `total_sales` in descending order.
7) **LIMIT**:
   - Returns only the top 10 rows.

## V. Advance Features

### V.A. Joining Data Sources

Joining data sources is possible with `leftJoin` and `innerJoin` methods. The following example demonstrates a
left join between **XML** and **JSON** file.

```php
use FQL\Stream\JsonStream as Json;
use FQL\Stream\Xml;
use FQL\Enum\Operator;
use FQL\Query\Debugger;

$ordersFile = Xml::open(__DIR__ . '/data/orders.xml');
$orders = $ordersFile->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order');
    
Debugger::inspectQuery($orders);

$usersFile = Json::open(__DIR__ . '/data/users.json');
$users = $usersFile->query()
    ->selectAll()
    ->from('data.users')
    ->leftJoin($orders, 'o')
        ->on('id', Operator::EQUAL, 'userId')
    ->where('o.totalPrice', Operator::EQUAL_STRICT, null);

Debugger::inspectQuery($users);
```

For results try the `composer example:join` command.

### V.B. Aggregations and Functions

Aggregations and functions are useful for manipulating data in queries. You can use them in `select` method. Functions values may be sets
as aliases which can be chained with previously called functions.

```php
// try to get sell price from actionPrice or price and round it to 2 decimal places
$json->query()
    ->coalesce('actionPrice', 'price')->as('sellPrice') 
    ->round('sellPrice', 2)->as('roundedPrice');
```

#### Aggregate Functions

Like in SQL, you can use these functions to aggregate data.

**avg(**_string_`$field`**):**`Query`

Select average value of column

```php
$query->avg('price')
    ->as('avg'); // SELECT AVG(price) AS avg
```

**count(**_string_`$field = null`**):**`Query`

Count number of rows. When `$field` at `$row` does not exist then it counts only rows with non-null values.

```php
$query->count('id')
    ->as('count'); // SELECT COUNT(id) AS count
// or
$query->count()
    ->as('count'); // SELECT COUNT(*) AS count
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

**sum(**_string_`$field`**):**`Query`

Select sum of values

```php
$query->sum('price')
    ->as('sum'); // SELECT SUM(price) AS sum
```

#### Hashing functions

**md5(**_string_`$field`**):**`Query`

MD5 algorithm for hashing

```php
$query->md5('name')
    ->as('hash'); // SELECT MD5(name) AS hash
```

**sha1(**_string_`$field`**):**`Query`

SHA1 algorithm for hashing

```php
$query->sha1('name')
    ->as('hash'); // SELECT SHA1(name) AS hash
```

#### Math Functions

**ceil(**_string_`$field`**):**`Query`

Round number up

```php
$query->ceil('price')
    ->as('ceilPrice') // SELECT CEIL(price) AS ceilPrice
```

**floor(**_string_`$field`**):**`Query`

Round number down

```php
$query->floor('price')
    ->as('floorPrice') // SELECT FLOOR(price) AS floorPrice
```

**modulo(**_string_`$field`**,** _int_`$divisor`**):**`Query`

Modulo operation, when divisor is zero then exception is thrown

```php
$query->modulo('price', 2)
    ->as('modPrice') // SELECT MOD(price, 2) AS modPrice
```

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

#### String Functions

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

**reverse(**_string_`$field`**):**`Query`

Reverse string

```php
$query->reverse('name')
    ->as('reversedName'); // SELECT REVERSE(name) AS reversedName
```

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

**from_base64(**_string_`$field`**):**`Query`

Decode base64 string

```php
$query->fromBase64('base64String')
    ->as('decodedString'); // SELECT FROM_BASE64(base64String) AS decodedString
```

**to_base64(**_string_`$field`**):**`Query`

Encode string to base64

```php
$query->toBase64('string')
    ->as('base64String'); // SELECT TO_BASE64(string) AS base64String
```

**randomString(**_int_`$length`**):**`Query`

Generates random string from predefined charset `abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`

```php
$query->randomString(16)
    ->as('randomString'); // SELECT RANDOM_STRING(16) AS randomString
```

#### Utils Functions

**coalesce(**_string_`...$fields`**)**:`Query`

Coalesce values (first non-null value)

```php
$query->coalesce('whatever', 'ArticleNr')
    ->as('coalesceString'); // SELECT COALESCE(ArticleNr, whatever) AS coalesceString
```

**coalesceNotEmpty(**_string_`...$fields`**)**:`Query`

Coalesce values when not empty (first non-empty value)

```php
$query->coalesceNotEmpty('whatever', 'ArticleNr')
    ->as('coalesceString'); // SELECT COALESCE_NE(whatever, ArticleNr) AS coalesceString
```

**randomBytes(**_int_`$length`**):**`Query`

Generates cryptographically secure random bytes. Suitable for generating salts, keys, and nonces.

```php
$query->randomBytes(16)
    ->as('randomBytes'); // SELECT RANDOM_BYTES(16) AS randomBytes
```

### V.C. Sorting and Filtering

#### Sorting Functions

**F**i**Q**ue**L**a now supports basic operators for filtering data like `ASC`, `DESC`, `SHUFFLE` and `NATSORT`.

```php
use FQL\Enum\Operator;

$results = $json->query()
    ->select('name, age')
    ->from('users')
    ->where('age', Operator::GREATER_THAN, 18)
    ->orderBy('name')->asc()
    ->orderBy('age')->desc()
    ->orderBy('name')->natural()
    ->orderBy('age')->shuffle();
```

> ⚠️ **Note**: `SHUFFLE` and `NATSORT` are experimental functions because they are too slow for datasets with many records,
But feel free to try them.

#### Filtering functions

Using `HAVING` is useful when you want to filter data based on final row or aggregated values. `HAVIN` not translate any
nested values, but `WHERE` clause does.

```php
use FQL\Enum\Operator;

$query = $json->query();

$results = $query
    ->select('name')
    ->round('price', 0)->as('roundedPrice')
    ->avg('price')->as('avgPrice')
    ->from('products')
    ->where('currency.code', Operator::EQUAL_STRICT, 'USD')
    ->having('roundedPrice', Operator::GREATER_THAN, 125)
        ->and('avgPrice', Operator::GREATER_THAN, 90);
```

### V.D. Pagination and Limits

You can limit the number of results returned by using the `LIMIT` and `OFFSET` clauses. This is useful for paginating.

```php
$results = $query
    ->select('name, age')
    ->from('users')
    ->limit(20)
    ->offset(40);
```

Or using by `page()` method:

```php
$results = $query
    ->select('name, age')
    ->from('users')
    ->page(2, 20);
```

### V.E. SQL Integration

#### Interpreted SQL

All fluent queries can be converted into SQL strings.

```php
$query = JsonStream::open(__DIR__ . '/data/products.tmp')->query();
$query->selectAll()
    ->select('brand.code')->as('brandCode')
    ->groupConcat('id', '/')->as('products')
    ->sum('price')->as('totalPrice')
    ->count('productId')->as('productCount')
    ->from('data.products')
    ->where('price', Operator::LESS_THAN, 300)
    ->or('price', Operator::GREATER_THAN, 400)
    ->groupBy('brand.code')
    ->orderBy('productCount')->desc();

echo $query->test();
```

`test()` produce output represents the query in SQL format. This feature is particularly useful for debugging and gaining
a clear understanding of how queries are constructed and executed.

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
GROUP BY brand.code
ORDER BY productCount DESC
```

#### Using SQL Strings

Parse SQL strings directly into queries for all supported file formats. Idea is to use SQL strings for creating queries
without fluent syntax. Now it could be used only for simple queries directly to files. Newly support `GROUP BY`, `OFFSET`,
multiple sorting and `SELECT` [functions](#vb-aggregations-and-functions) (All of them).

> ⚠️ Parser still does not support `JOIN` clause and some logical operators `IN`, `NOT_IN`,
`CONTAINS`, `STARTS_WITH` and `ENDS_WITH`.

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

Using SQL strings for querying XML data:

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

$results = $xml->sql($sql)
    ->fetchAll();
Debugger::dump(iterator_to_array($results));
```

> ⚠️ In the future, it will be possible to use `FROM` and `JOIN` to directly load data from files and use all results
types from another queries.


### V.F. Query Inspection and Benchmarking

If you want use inspecting and benchmarking queries, you need to use `FQL\Query\Debugger` class. Dumping variables and
cli output require `tracy/tracy` package if you are not using it, you can install it by:

```bash
composer require --dev tracy/tracy
```

Start debugger at the beginning of your script.

```php
use FQL\Query\Debugger;

Debugger::start();
```

#### Inspect Queries
You can inspect your query for mor information about execution time, memory usage, SQL query and results.

```php
use FQL\Stream\Xml;

$ordersFile = Xml::open(__DIR__ . '/data/orders.xml');
$query = $ordersFile->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order');

Debugger::inspectQuery($query);
```

Or inspect query string which shows different between input SQL and applied SQL

```php
Debugger::inspectQuerySql(
    $ordersFile,
    "SELECT id, user_id, total_price FROM orders.order"
);
```

#### Benchmarking

You can benchmark your queries and test their performance through the number of iterations.

```php
use FQL\Stream\Xml;

$query = Xml::open(__DIR__ . '/data/orders.xml')->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order')

Debugger::benchmarkQuery($query, 1000);
```

#### Final results

```php
Debugger::end();
die();
```

Will output final results like this:

```
'=============================='
>> Final execution time (s): 2.019434
>> Final execution time (ms): 2019.434
>> Final execution time (µs): 2019434
```

For more information about inspecting queries and benchmarking, see the [examples](#vi-examples)

## VI. Examples

Check the examples and run them using Composer. All examples uses `\FQL\Helpers\Debugger` and methods `inspectQuery` or
`inspectQuerySql` or `benchmarkQuery` to show the results.

```bash
composer examples
# or
composer example:csv
composer example:join
composer example:json
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
>> GROUP BY o.orderId
>> ORDER BY totalPrice DESC
'================'
'*** Results: ***'
'================'
> Result class: FQL\Results\InMemory
> Count: 5
'=================='
'*** First row: ***'
'=================='
array (4)
   'id' => 2
   'name' => 'John Doe 2'
   'orderId' => 3
   'totalPrice' => 600

'------------------------------'
> Memory usage: 2.0601MB (emalloc)
> Memory peak usage: 2.1227MB (emalloc)
'------------------------------'
> Execution time (s): 0.021444
> Execution time (ms): 21.444
> Execution time (µs): 21444
'========================'
'*** Benchmark Query: ***'
'========================'
> 100 iterations
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
>> GROUP BY o.orderId
>> ORDER BY totalPrice DESC
'========================='
'*** STREAM BENCHMARK: ***'
'========================='
> Size (KB): 2.8
> Count: 5
> Iterated results: 500
'------------------------------'
> Memory usage: 2.0583MB (emalloc)
> Memory peak usage: 2.1227MB (emalloc)
'------------------------------'
> Execution time (s): 0.026855
> Execution time (ms): 26.855
> Execution time (µs): 26855
'========================'
'*** PROXY BENCHMARK: ***'
'========================'
> Size (KB): 0.72
> Count: 5
> Iterated results: 500
'------------------------------'
> Memory usage: 2.0603MB (emalloc)
> Memory peak usage: 2.1227MB (emalloc)
'------------------------------'
> Execution time (s): 0.001072
> Execution time (ms): 1.072
> Execution time (µs): 1072
'=============================='
> Memory usage: 2.058MB (emalloc)
> Memory peak usage: 2.1227MB (emalloc)
>> Final execution time (s): 0.049452
>> Final execution time (ms): 49.452
>> Final execution time (µs): 49452
```

## VII. Knowing issues

- ⚠️ You can use `WHERE` clause only for one logical group. Nesting groups is not supported yet.
- ⚠️ Functions `JOIN`, `ORDER BY` and `GROUP BY` are not memory efficient, because joining data or sorting data requires 
to load all data into memory. It may cause memory issues for large datasets. But everything else is like ⚡️.
- ⚠️ SQL - Supports SQL string queries inspired with SQL-like syntax. Syntax does not support yet all SQL fluent features.
- ⚠️ Automatic conversion of queries into SQL-like syntax. It is not fully compatible yet with SQL parser

## VIII. Planning Features

- [ ] **Next file formats**: Add next file formats like [NDJson](https://github.com/ndjson/ndjson-spec) and [MessagePack](https://msgpack.org/)
- [ ] **Improve SQL parser**: SQL parser will be more complex. Will add support for direct selecting files like
`FROM [csv:file.tmp]` or `JOIN([./subdir/file.json].data.users)`. It will bring support to all features from fluent
**F**i**Q**ue**L**a.
- [ ] **DELETE, UPDATE, INSERT**: Support for manipulating data in files.
- [ ] **Documentation**: Create detailed guides and examples for advanced use cases.
- [ ] **Tests**: Increase test coverage.

## IX. Contributions

If you have suggestions or would like to contribute to these features, feel free to open an issue or a pull request!

**How to contribute:**
- Fork the repository
- Create a new branch
- Make your changes
- Create a pull request
- All tests must pass
- Wait for approval
- 🚀
