# FiQueLa: File Query Language 

> _[fi-kju-ela]_

![Packagist Version](https://img.shields.io/packagist/v/1biot/fiquela)
[![CI](https://github.com/1biot/fiquela/actions/workflows/ci.yml/badge.svg)](https://github.com/1biot/fiquela/actions/workflows/ci.yml)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/fiquela/php)
![Packagist License](https://img.shields.io/packagist/l/1biot/fiquela)

![Coverage](https://img.shields.io/badge/coverage-83.68%25-yellow)
![PHPUnit Tests](https://img.shields.io/badge/PHPUnit-tests%3A_691-lightgreen)
![PHPStan](https://img.shields.io/badge/phpstan-level_8-lightgreen)

**F**i**Q**ue**L**a lets you query files like a database, with SQL-like syntax or a fluent PHP API.
Filter, join, group, aggregate, and transform data from **XML**, **XLS**, **ODS**, **CSV**, **JSON**, **NDJSON**,
**YAML**, and **NEON** without setting up a database. It is built for real-world data processing with streaming support,
explain/debug tooling, and strongly typed operators.

**Features**:

- 📂 **Supports multiple formats**: Work seamlessly with XML, CSV, JSON, NDJSON, YAML, NEON, and XLS.
- 🛠️ **SQL-inspired syntax**: Perform `SELECT`, `JOIN`, `WHERE`, `GROUP BY`, `HAVING`, `ORDER BY` and more.
- ✍️ **Flexible querying**: Write SQL-like strings or use the fluent API.
- 📊 **Powerful expressions and functions**: Use `CASE WHEN`, `IF`, grouped conditions, `XOR`, `REGEXP`, aggregates, and utility functions.
- 🚀 **Stream-first processing**: Optimized for large JSON, XML, and CSV files with low memory pressure where possible.
- 🧑‍💻 **Developer-Friendly**: Map results to DTOs for easier data manipulation.
- ⭐ **Unified API across all supported formats**: Use a consistent API for all your data needs.

### Why FiQueLa

- Query files with familiar SQL concepts while keeping everything in PHP.
- Join data across sources and formats in one query.
- Handle advanced logic with nested condition groups and statement functions.
- Inspect execution using explain output and debugger tooling.

**Table of Contents**:

- [Overview](#1-overview)
- [Installation](#2-installation)
- [Supported Formats](#3-supported-formats)
- [Getting Started](#4-getting-started)
- [Documentation](#5-documentation)
  - [Opening Files](docs/opening-files.md)
  - [Fluent API](docs/fluent-api.md)
  - [File Query Language](docs/file-query-language.md)
  - [Fetching Data](docs/fetching-data.md)
  - [Query Life Cycle](docs/query-life-cycle.md)
  - [Query Inspection and Benchmarking](docs/query-inspection-and-benchmarking.md)
  - [API Reference](docs/api-reference.md)
- [Examples](#6-examples)
- [Ecosystem](#7-ecosystem)
  - [FiQueLa CLI](#fiquela-cli)
  - [FiQueLa API](#fiquela-api)
  - [FiQueLa Studio](#fiquela-studio)
- [Known issues](#8-known-issues)
- [Roadmap](#9-roadmap)
- [Contributions](#10-contributions)

## 1. Overview

Why limit SQL to databases when it can be just as effective for structured files?
**F**i**Q**ue**L**a brings SQL-like querying to file-based data and keeps your workflow fully in PHP.

Key highlights:
- **Universal querying**: Filter, sort, join, and aggregate data across multiple file formats.
- **Real SQL-like behavior**: Use `GROUP BY`, `HAVING`, nested conditions, `CASE WHEN`, `IF`, and many built-in functions.
- **Flexible integration**: Query through fluent API or SQL-like strings, whichever matches your use case.
- **Operational tooling**: Use debugger and explain plans to understand performance and execution.

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
composer require tracy/tracy
```

### Dependencies

- **`league/csv`**: Required for CSV file support.
- **`halaxa/json-machine`**: Required for JSON stream support.
- **`symfony/yaml`**: Required for YAML file support.
- **`nette/neon`**: Required for NEON file support.
- **`openspout/openspout`**: Required for XLSX and ODS file support.
- **`tracy/tracy`**: Optional for using Debugger

## 3. Supported Formats

| Format      | Name                    | Class                   | File Support | String Support |
|-------------|-------------------------|-------------------------|--------------|----------------|
| `csv`       | CSV                     | `FQL\Stream\Csv`        | ✅            | ❌              |
| `xml`       | XML                     | `FQL\Stream\Xml`        | ✅            | ❌              |
| `xls`       | XLS/XLSX                | `FQL\Stream\Xls`        | ✅            | ❌              |
| `ods`       | ODS                     | `FQL\Stream\Ods`        | ✅            | ❌              |
| `jsonFile`  | JSON Stream             | `FQL\Stream\JsonStream` | ✅            | ❌              |
| `ndJson`    | Newline Delimited JSON  | `FQL\Stream\NDJson`     | ✅            | ❌              |
| `json`      | JSON (json_decode)      | `FQL\Stream\Json`       | ✅            | ✅              |
| `yaml`      | YAML                    | `FQL\Stream\Yaml`       | ✅            | ✅              |
| `neon`      | NEON                    | `FQL\Stream\Neon`       | ✅            | ✅              |


### Directory provider

Is special provider `FQL\Stream\Dir` class. It allows you to use directory as a source.
You can query all files recursively by queries.

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

This query returns rows that match either a specific EAN or a price threshold, sorted by price and limited to 10 records.

Or using the FQL syntax:

```php
use FQL\Query;

$query = <<<FQL
    SELECT *
    FROM xml(./path/to/file.xml).SHOP.SHOPITEM
    WHERE
        EAN = "1234567891011"
        OR PRICE <= 200
    ORDER BY PRICE DESC
    LIMIT 10
FQL;
$results = Query\Provider::fql($query)
    ->execute()
    ->fetchAll();

print_r(iterator_to_array($results));
````


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
- [Query Life Cycle](docs/query-life-cycle.md)
- [Query Inspection and Benchmarking](docs/query-inspection-and-benchmarking.md)
- [API Reference](docs/api-reference.md)

## 6. Examples

Check the examples and run them using Composer. All examples uses `\FQL\Query\Debugger` and methods
`inspectQuery`, `inspectSql`, `inspectStreamSql` or `benchmarkQuery` to show the results.

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
composer example:explain
```

Check step **Examples** at [actions](https://github.com/1biot/fiquela/actions/runs/12992585648/job/36232767074) or run
`composer example:csv` and output will look like this:

```
=========================
### Debugger started: ###
=========================
> Memory usage (MB): 1.3191 (emalloc)
> Memory peak usage (MB): 1.7326 (emalloc)
------------------------------
> Execution time (s): 8.5E-5
> Execution time (ms): 0.085
> Execution time (µs): 85
> Execution memory peak usage (MB): 0
=========================
### Inspecting query: ###
=========================
==================
### SQL query: ###
==================
> SELECT
>   ean ,
>   defaultCategory ,
>   EXPLODE(defaultCategory, " > ") AS categoryArray ,
>   price ,
>   ROUND(price, 2) AS price_rounded ,
>   MOD(price, 100) AS modulo_100 ,
>   MOD(price, 54) AS modulo_54
> FROM csv(products-w-1250.csv, "windows-1250", ";").*
> GROUP BY defaultCategory
> ORDER BY defaultCategory DESC
================
### Results: ###
================
> Result class: FQL\Results\InMemory
> Results size memory (KB): 3.55
> Result exists: true
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
> Memory usage (MB): 3.1451 (emalloc)
> Memory peak usage (MB): 3.2262 (emalloc)
------------------------------
> Execution time (s): 0.040016
> Execution time (ms): 40.016
> Execution time (µs): 40016
> Execution memory peak usage (MB): 1.4936
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
>   EXPLODE(defaultCategory, " > ") AS categoryArray ,
>   price ,
>   ROUND(price, 2) AS price_rounded ,
>   MOD(price, 100) AS modulo_100 ,
>   MOD(price, 54) AS modulo_54
> FROM csv(products-w-1250.csv, "windows-1250", ";").*
> GROUP BY defaultCategory
> ORDER BY defaultCategory DESC
=========================
### STREAM BENCHMARK: ###
=========================
> Size (KB): 2.78
> Count: 15
> Iterated results: 37 500
>>> SPLIT TIME <<<
> Memory usage (MB): 3.1347 (emalloc)
> Memory peak usage (MB): 3.2262 (emalloc)
------------------------------
> Execution time (s): 36.402098
> Execution time (ms): 36402.098
> Execution time (µs): 36402098
> Execution memory peak usage (MB): 0
============================
### IN_MEMORY BENCHMARK: ###
============================
> Size (KB): 3.55
> Count: 15
> Iterated results: 37 500
>>> SPLIT TIME <<<
> Memory usage (MB): 3.1451 (emalloc)
> Memory peak usage (MB): 3.2262 (emalloc)
------------------------------
> Execution time (s): 0.01743
> Execution time (ms): 17.43
> Execution time (µs): 17430
> Execution memory peak usage (MB): 0
=======================
### Debugger ended: ###
=======================
> Memory usage (MB): 3.1343 (emalloc)
> Memory peak usage (MB): 3.2262 (emalloc)
------------------------------
> Final execution time (s): 36.459756
> Final execution time (ms): 36459.756
> Final execution time (µs): 36459756
```

## 7. Ecosystem

FiQueLa is more than just a PHP library. It comes with a CLI tool, a REST API server, and a web-based query explorer.

### FiQueLa CLI

[**fiquela-cli**](https://github.com/1biot/fiquela-cli) is a command-line tool for querying structured files directly from the terminal. It supports local file querying, remote API connections, and an interactive REPL mode with paginated table output.

```bash
# Install
curl -fsSL https://raw.githubusercontent.com/1biot/fiquela-cli/main/install.sh | bash

# Query a local file
fiquela-cli --file=data.csv "SELECT name, price FROM * WHERE price > 100;"

# Interactive mode
fiquela-cli --file=data.csv
```

Requires PHP 8.2+ with readline, curl, and zlib extensions.

### FiQueLa API

[**fiquela-api**](https://github.com/1biot/fiquela-api) is a RESTful server for querying structured files over HTTP. It provides file management, query execution, result export (CSV, TSV, JSON), and query history tracking with JWT authentication.

[![Deploy to DO](https://www.deploytodo.com/do-btn-blue.svg)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/1biot/fiquela-api/tree/main?refcode=92025543cb9f)

Requires credentials configuration via environment variables. Optionally enable S3 backup (Cloudflare R2) for file storage. For more information visit the [repository](https://github.com/1biot/fiquela-api?tab=readme-ov-file#-credentials).

Key endpoints: `POST /api/auth/login` for JWT authentication, `POST /api/v1/query` for executing queries, `GET /api/v1/files` for file management, `GET /api/v1/export/{hash}` for downloading results. All endpoints except login require `Authorization: Bearer <token>`.

### FiQueLa Studio

[**studio.fiquela.io**](https://studio.fiquela.io) is a web-based visual query explorer for building and running FQL queries interactively. Requires a running [FiQueLa API](#fiquela-api) instance to connect to.

## 8. Known issues

- ⚠️ Functions `JOIN`, and `ORDER BY` are not memory efficient, because joining data or sorting data requires 
to load all data into memory. It may cause memory issues for large datasets. But everything else is like ⚡️.

## 9. Roadmap

- [x] ~~**Operator BETWEEN**: Add operator `BETWEEN` for filtering data and add support for dates and ranges.~~
- [x] ~~**XLS/XLSX**: Add Excel file support.~~
- [x] ~~**Custom cast type**: Add support for custom cast type for `SELECT` clause.~~
- [x] ~~**Add explain method**: Add method `explain()` for explaining query execution from actual query debugger and provide more complex information about query.~~
- [x] ~~**PHPStan 8**: Fix all PHPStan 8 errors.~~
- [x] ~~**Tests**: Increase test coverage (80%+).~~
- [x] ~~**Optimize GROUP BY**: Optimize `GROUP BY` for more memory efficient data processing.~~
- [x] ~~**DELETE, UPDATE, INSERT**: Support for manipulating data in files.~~ ~~- Instead of this, it will comes support
for exporting data to files (CSV, NDJson, MessagePack, and more...) by `INTO` clause.~~
- [ ] **Next file formats**: Add next file formats [MessagePack](https://msgpack.org/), [Parquet](https://parquet.apache.org/docs/file-format/), [INI](https://en.wikipedia.org/wiki/INI_file) and [TOML](https://toml.io/en/)
- [ ] **Documentation**: Create detailed guides and examples for advanced use cases.
- [ ] **Tests**: Increase test coverage (90%+).
- [ ] **Hashmap cache**: Add hashmap cache (Redis, Memcache) for more memory efficient data processing.


## 10. Contributions

If you have suggestions or would like to contribute to these features, feel free to open an issue or a pull request!

**How to contribute:**
- Fork the repository
- Create a new branch
- Make your changes
- Create a pull request
- All tests must pass
- Wait for approval
- 🚀
