# FiQueLa: File Query Language 

> _[fi-kju-ela]_

![Packagist Version](https://img.shields.io/packagist/v/1biot/uniquel)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/1biot/uniquel/ci.yml)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/uniquel/php)
![Static Badge](https://img.shields.io/badge/PHPUnit-tests%3A_171-lightgreen)
![Static Badge](https://img.shields.io/badge/PHPUnit-asserts%3A_596-lightgreen)
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

- [Overview](#1-overview)
- [Installation](#2-installation)
- [Supported Formats](#3-supported-formats)
- [Getting Started](#4-getting-started)
- [Documentation](#5-documentation)
- [Query Life Cycle](#6-query-life-cycle)
- [Query Inspection and Benchmarking](#7-query-inspection-and-benchmarking)
- [Examples](#8-examples)
- [Knowing issues](#9-knowing-issues)
- [Planning Features](#10-planning-features)
- [Contributions](#11-contributions)

## 1. Overview

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

## 2. Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require 1biot/fiquela
```

Install packages for optional features:

```bash
composer require league/csv halaxa/json-machine symfony/yaml nette/neon tracy/tracy
```

- **`league/csv`**: Required for CSV file support.
- **`halaxa/json-machine`**: Required for JSON stream support.
- **`symfony/yaml`**: Required for YAML file support.
- **`nette/neon`**: Required for NEON file support.
- **`tracy/tracy`**: Optional for using Debugger

## 3. Supported Formats

| Format     | Name               | File Support | String Support | Dependencies                                   |
|------------|--------------------|--------------|----------------|------------------------------------------------|
| `csv`      | CSV                | ✅            | ❌              | `league/csv`                                   |
| `xml`      | XML                | ✅            | ❌              | `ext-xmlreader`, `ext-simplexml`, `ext-libxml` |
| `jsonFile` | JSON Stream        | ✅            | ❌              | `halaxa/json-machine`                          |
| `json`     | JSON (json_decode) | ✅            | ✅              | `ext-json`                                     |
| `yaml`     | YAML               | ✅            | ✅              | `symfony/yaml`                                 |
| `neon`     | NEON               | ✅            | ✅              | `nette/neon`                                   |

## 4. Getting Started

Here’s a quick example of how **F**i**Q**ue**L**a can simplify your data queries:

```php
use FQL\Enum;
use FQL\Query;

$results = Query\Provider::fromFileQuery('(./path/to/file.xml).SHOP.SHOPITEM')
    ->selectAll()
    ->where('EAN', Enum\Operator::EQUAL, '1234567891011')
    ->or('PRICE', Enum\Operator::LESS_THAN_OR_EQUAL, 200)
    ->orderBy('PRICE')->desc()
    ->limit(10)
    ->execute()
    ->fetchAll();

print_r(iterator_to_array($results));
```

Output:

```php
Array
(
    [0] => Array
        (
            [NAME] => "Product 1"
            [EAN] => "1234567891011"
            [PRICE] => 300.00
        )
    [1] => Array
        (
            [NAME] => "Product 2"
            [EAN] => "1234567891012"
            [PRICE] => 150.00
        )
    [2] => Array
        (
            [NAME] => "Product 3"
            [EAN] => "1234567891013"
            [PRICE] => 150.00
        )
    ...
)
```


## 5. Documentation

For more details about **F**i**Q**ue**L**a and her capabilities, explore the documentation sections.

- [Opening Files](docs/opening-files.md)
- [Fluent API](docs/fluent-api.md)
- [File Query Language](docs/file-query-language.md)
- [Fetching Data](docs/fetching-data.md)

## 6. Query Life Cycle

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

> ⚠️ Functions can only be utilized in the `SELECT` clause. To filter results based on a function's value, use the `HAVING`
clause, and to sort results, use the `ORDER BY` clause. In both `HAVING` and `ORDER BY`, the function can only be
referenced by its alias.
___

**Example Query Execution**:

For the query:

```sql
SELECT
    name,
    SUM(sales) AS total_sales
FROM (customers.xml).customers.customer
WHERE age > 30
GROUP BY name
HAVING total_sales > 1000
ORDER BY total_sales DESC
LIMIT 10;
```

**Execution Order**:
1) **FROM** and **JOIN**:
   - Data are loaded from the employees file `customers.xml`. And using `customers.customer` as path.
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

### 7. Query Inspection and Benchmarking

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

This method stops the debugger and outputs the final results. 

```php
Debugger::end();
```

For more information about inspecting queries and benchmarking, see the [examples](#8-examples)

## 8. Examples

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
composer example:xml
composer example:yaml
```

Runs `composer example:csv` and output will look like this:

```
=========================
### Debugger started: ###
=========================
> Memory usage (MB): 1.1899 (emalloc)
> Memory peak usage (MB): 1.5636 (emalloc)
------------------------------
> Execution time (s): 5.3E-5
> Execution time (ms): 0.053
> Execution time (µs): 53
=========================
### Inspecting query: ###
=========================
==================
### SQL query: ###
==================
> SELECT
>   ean ,
>   defaultCategory ,
>   EXPLODE(" > ", defaultCategory) AS categoryArray ,
>   price ,
>   ROUND(price, 2) AS price_rounded ,
>   MOD(price, 100) AS modulo_100 ,
>   MOD(price, 54) AS modulo_54
> FROM [csv](products-w-1250.csv).*
> GROUP BY defaultCategory
> ORDER BY defaultCategory DESC
================
### Results: ###
================
> Result class: FQL\Results\InMemory
> Results size memory (KB): 3.55
> Result exists: TRUE
> Result count: 15
========================
### Fetch first row: ###
========================
array (7)
   'ean' => 5010232964877
   'defaultCategory' => 'Testování > Drogerie'
   'categoryArray' => array (2)
   |  0 => 'Testování'
   |  1 => 'Drogerie'
   'price' => 121.0
   'price_rounded' => 121.0
   'modulo_100' => 21.0
   'modulo_54' => 13.0

>>> SPLIT TIME <<<
> Memory usage (MB): 2.4509 (emalloc)
> Memory peak usage (MB): 2.5911 (emalloc)
------------------------------
> Execution time (s): 0.024494
> Execution time (ms): 24.494
> Execution time (µs): 24494
========================
### Benchmark Query: ###
========================
> 2 500 iterations
==================
### SQL query: ###
==================
> SELECT
>   ean ,
>   defaultCategory ,
>   EXPLODE(" > ", defaultCategory) AS categoryArray ,
>   price ,
>   ROUND(price, 2) AS price_rounded ,
>   MOD(price, 100) AS modulo_100 ,
>   MOD(price, 54) AS modulo_54
> FROM [csv](products-w-1250.csv).*
> GROUP BY defaultCategory
> ORDER BY defaultCategory DESC
=========================
### STREAM BENCHMARK: ###
=========================
> Size (KB): 2.61
> Count: 15
> Iterated results: 37 500
>>> SPLIT TIME <<<
> Memory usage (MB): 2.4404 (emalloc)
> Memory peak usage (MB): 2.6138 (emalloc)
------------------------------
> Execution time (s): 10.107137
> Execution time (ms): 10107.137
> Execution time (µs): 10107137
============================
### IN_MEMORY BENCHMARK: ###
============================
> Size (KB): 3.55
> Count: 15
> Iterated results: 37 500
>>> SPLIT TIME <<<
> Memory usage (MB): 2.4509 (emalloc)
> Memory peak usage (MB): 2.6138 (emalloc)
------------------------------
> Execution time (s): 0.00673
> Execution time (ms): 6.73
> Execution time (µs): 6730
=======================
### Debugger ended: ###
=======================
> Memory usage (MB): 2.44 (emalloc)
> Memory peak usage (MB): 2.6138 (emalloc)
------------------------------
> Final execution time (s): 10.138434
> Final execution time (ms): 10138.434
> Final execution time (µs): 10138434
```

## 9. Knowing issues

- ⚠️ Functions `JOIN`, `ORDER BY` and `GROUP BY` are not memory efficient, because joining data or sorting data requires 
to load all data into memory. It may cause memory issues for large datasets. But everything else is like ⚡️.

## 10. Planning Features

- [ ] **Operator BETWEEN**: Add operator `BETWEEN` for filtering data and add support for dates and ranges.
- [ ] **Next file formats**: Add next file formats like [NDJson](https://github.com/ndjson/ndjson-spec) and [MessagePack](https://msgpack.org/)
- [ ] **Documentation**: Create detailed guides and examples for advanced use cases.
- [ ] **Add explain method**: Add method `explain()` for explaining query execution from actual query debugger and provide more complex information about query.
- [ ] **Tests**: Increase test coverage.
- [ ] **Optimize GROUP BY**: Optimize `GROUP BY` for more memory efficient data processing.
- [ ] **Hashmap cache**: Add hashmap cache (Redis, Memcache) for more memory efficient data processing.
- [ ] ~~**DELETE, UPDATE, INSERT**: Support for manipulating data in files.~~ - Instead of this, it will comes support
for exporting data to files (CSV, NDJson, MessagePack, and more...).

## 11. Contributions

If you have suggestions or would like to contribute to these features, feel free to open an issue or a pull request!

**How to contribute:**
- Fork the repository
- Create a new branch
- Make your changes
- Create a pull request
- All tests must pass
- Wait for approval
- 🚀
