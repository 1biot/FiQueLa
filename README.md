# UniQueL
UniQueL __/yu-nik-ju-el/__ (Universal Query Language) is a PHP library for easy manipulation of JSON or Yaml or Neon data. It offers
SQL-inspired syntax for querying, filtering, and aggregating data. The library is designed with a focus on modularity,
simplicity, and efficiency.

![Packagist Version](https://img.shields.io/packagist/v/1biot/jql)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/jql/php)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/1biot/jql/ci.yml)
![Packagist Downloads](https://img.shields.io/packagist/dm/1biot/jql)
![Packagist License](https://img.shields.io/packagist/l/1biot/jql)

## Table of Contents
- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
- [Examples](#examples)

## Features
- [x] **JSON, YAML, NEON** - Load data from JSON, YAML, or NEON files or strings.
- [x] **SELECT** - Select columns and their aliases.
- [x] **FROM** - Define the data source.
- [x] **WHERE** - Filter data by conditions through many operators like equal, not equal, greater than, etc.
- [x] **ORDER BY** - Sort data by columns.
- [x] **LIMIT** - Limit the number of results.
- [x] **OFFSET** - Skip a number of results.
- [x] **AGGREGATE FUNCTIONS** - Sum, average, count, etc.
- [x] **PAGINATION** - Paginate results.
- [x] **SQL** - View interpreted SQL query.
- [ ] Provide functions like coalesce, concat, if, etc.
- [ ] Add support for unlimited nesting of conditions.
- [ ] **HAVING** - filtering by aliased fields.
- [ ] Provide SQL parser for query data by sql string
- [ ] Support for CSV files
- [ ] **GROUP BY** - I don't know yet if it is necessary.
- [ ] **DELETE** - Delete data.
- [ ] **UPDATE** - Update data.
- [ ] **INSERT** - I don't know yet if it is necessary. Main problem is with consistency of data when you insert
  uncompleted data. 

## Installation
Use [Composer](https://getcomposer.org/) to install the UQL.

```bash
composer require 1biot/uniquel
```

## Usage

### 1. Loading Data

#### JSON
Native support for JSON data. You can load data from a file or a string. All formats implement the `UQL\Stream\Stream`
interface and can be used interchangeably.
```php
// loading of JSON data
use UQL\Stream\Json;

// from a file
$json = Json::open('data.json');

// Or a string
$json = Json::string(file_get_contents('data.json'));
```
#### YAML and NEON
This file formats are not supported by default. You need to install the necessary libraries.

```bash
composer require symfony/yaml # for YAML
composer require nette/neon # for NEON
```

```php
// loading of YAML and NEON data
use UQL\Stream\Yaml;
use UQL\Stream\Neon;

$yaml = Yaml::open('data.yaml');
$yaml = Yaml::string(file_get_contents('data.yaml'));

$neon = Neon::open('data.neon');
$neon = Neon::string(file_get_contents('data.neon'));
```

### 2. Querying Data

```php
use UQL\Enum\Operator;
use UQL\Enum\Sort;

$query = $file->query();

// Define a query
$results = $query
    ->select('id')
    ->select('name, age')
    ->from('users')
    ->where('age', Operator::GREATER_THAN, 18)
    ->orderBy('name', Sort::ASC);
```

### 4. Fetching Data

```php
$results = $query
    ->where('id', Operator::IN, [1, 2])
    ->fetchAll();

foreach ($results as $user) {
    echo '#' . $user['id']
        . ': '
        . $user['name']
        . ' (' . $user['age'] . ")\n";
}

// Output
// #1: John (20)
// #2: Jane (25)

// Fetch a single row
$user = $query->fetch();

// Fetch a single value
$ages = $query->fetchSingle('age');

// fetch nth row
$user = $query->fetchNth(2);
// or
$user = $query->fetchNth('even');
```

### 3. Aggregate Functions

```php
$results = $query->fetchAll();
$totalAge = $query->sum('age');
$averageAge = $query->avg('age');
$count = $query->count();
```

### 4. Pagination and limit

```php
// 20 results starting from the 40th record
$results = $query
    ->select('name, age')
    ->from('users')
    ->limit(20, 40);

// or

$results = $query
    ->select('name, age')
    ->from('users')
    ->offset(40)
    ->limit(20);
```

### 5. SQL
You can view interpreted SQL query. This SQL query is not executable yet but in the future, it will be possible to parse
sql queries through `sql()` method at `UQL\Stream\Stream` interface.

```php
use UQL\Enum\Operator;

$query->select('name, price')
    ->select('brand.name')->as('brand')
    ->from('data.products')
    ->where('brand.code', Operator::EQUAL, 'AD')
    ->and('name', Operator::NOT_EQUAL, 'Product B')
    ->or('name', Operator::EQUAL, 'Product B')
    ->or('price', Operator::GREATER_THAN_OR_EQUAL, 200)
    ->offset(1)
    ->limit(2);

echo $query->test();
// Output

// SELECT
// 	 name,
// 	 price,
// 	 brand.name AS brand 
// FROM data.products 
// WHERE (
// 	 brand.code = 'AD' 
// 	AND name != 'Product B'
// ) OR (
// 	 name = 'Product B' 
// 	AND price >= 200
// ) 
// OFFSET 1
// LIMIT 2

// in the future
$sql = <<<SQL
SELECT
    name, price, brand.name AS brand
FROM data.products
WHERE brand.code = 'AD'
SQL

$file->sql($sql);
```

## Examples

Go to [examples](examples) directory for more examples.
