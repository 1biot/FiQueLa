# UniQueL

![Packagist Version](https://img.shields.io/packagist/v/1biot/jql)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/jql/php)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/1biot/jql/ci.yml)
![Packagist Downloads](https://img.shields.io/packagist/dm/1biot/jql)
![Packagist License](https://img.shields.io/packagist/l/1biot/jql)

**UniQueL** __/yu-nik-ju-el/__ (**Universal Query Language**) is a PHP library for seamless manipulation of data in
**JSON**, **YAML**, **NEON**, and **XML** formats. The library provides an SQL-inspired syntax for querying, filtering,
and aggregating data. It is designed for simplicity, modularity, and efficiency.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Getting Started](#getting-started)
    - [Supported Formats](#supported-formats)
    - [Basic Querying](#basic-querying)
- [Advanced Usage](#advanced-usage)
    - [Aggregate Functions](#aggregate-functions)
    - [Pagination and Limit](#pagination-and-limit)
    - [Interpreted SQL](#interpreted-sql)
- [Roadmap](#roadmap)
- [Examples](#examples)

## Features

- ✅ Support for **JSON**, **YAML**, **NEON**, and **XML** (easily extensible to other formats).
- ✅ SQL-inspired capabilities:
    - **SELECT** for selecting fields and aliases.
    - **WHERE** for filtering with various operators.
    - **ORDER BY** for sorting.
    - **LIMIT** and **OFFSET** for pagination and result limits.
- ✅ Aggregation functions like `SUM`, `AVG`, and `COUNT`.
- ✅ Support for functions like `COALESCE`, `CONCAT`, and `IF` (in development).
- ✅ Automatic conversion of queries into SQL-like syntax.
- 🚀 Unified API across all supported formats.

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
    ->orderBy('name', Sort::ASC)
    ->fetchAll();

foreach ($results as $user) {
    echo "{$user['name']} is {$user['age']} years old.\n";
}
```

## Advanced Usage

### Aggregate Functions

```php
$totalAge = $query->sum('age');
$averageAge = $query->avg('age');
$count = $query->count();
```

### Pagination and Limit

```php
$results = $query
    ->select('name, age')
    ->from('users')
    ->limit(20, 40);
```

### Interpreted SQL

```php
$query->select('name, price')
    ->from('products')
    ->where('price', Operator::GREATER_THAN, 100)
    ->limit(10);

echo $query->test();
// Output:
// SELECT
//     name,
//     price
// FROM products
// WHERE price > 100
// LIMIT 10
```

## Roadmap

This section lists features and improvements planned for future releases:

- [ ] **Support for CSV**: Enable querying data directly from CSV files.
- [ ] **Functions**: Add advanced functions such as `COALESCE`, `CONCAT`, `IF`, and more.
- [ ] **HAVING Clause**: Enable filtering by aliases in queries.
- [ ] **GROUP BY**: Introduce support for grouping data.
- [ ] **DELETE, UPDATE, INSERT**: Support for manipulating data in JSON and XML formats.
- [ ] **SQL Parser**: Parse SQL strings into executable queries for all supported formats.
- [ ] **Unlimited Condition Nesting**: Enhance condition logic to allow unlimited nesting for complex queries.
- [ ] **Documentation**: Create detailed guides and examples for advanced use cases.
- [ ] **Integration Tests**: Add comprehensive test coverage for all supported formats.

If you have suggestions or would like to contribute to these features, feel free to open an issue or a pull request!

## Examples

Check the [examples](examples) directory for more detailed usage examples.
