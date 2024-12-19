# JQL - JSON Query Language
JQL (JSON Query Language) is a PHP library for easy manipulation of JSON data. It offers SQL-inspired syntax for querying, filtering, and aggregating data. The library is designed with a focus on modularity, simplicity, and efficiency.

![Packagist Version](https://img.shields.io/packagist/v/1biot/jql)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/jql/php)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/1biot/jql/ci.yml)
![Packagist Downloads](https://img.shields.io/packagist/dm/1biot/jql)
![Packagist License](https://img.shields.io/packagist/l/1biot/jql)

## Table of Contents
- [Installation](#installation)
- [Usage](#usage)
- [API](#api)
  - [Methods](#enums)
  - [Enums](#enums)
  - [Exceptions](#exceptions)
  - [Traits](#traits)
- [Examples](#examples)

## Installation
Use [Composer](https://getcomposer.org/) to install the JQL.

```bash
composer require 1biot/jql
```

## Usage

### 1. Loading JSON Data

```php
use JQL\Json;

// Load data from a file
$json = Json::open('data.json');

// Or load data from a string
$json = Json::string(file_get_contents('data.json'));
```

### 2. Querying Data

```php
use JQL\QueryProvider;

$query = $json->query();

// Define a query
$results = $query
    ->select('id')
    ->select('name, age')
    ->from('users')
    ->where('age', Operator::GREATER_THAN, 18)
    ->orderBy('name', Sort::ASC)
    ->fetchAll();

foreach ($results as $user) {
    echo '#' . $user['id'] . ': ' . $user['name'] . ' (' . $user['age'] . ")\n";
}
```

### 3. Aggregate Functions

```php
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
    ->limit(20, 40)
    ->fetchAll();

// or

$results = $query
    ->select('name, age')
    ->from('users')
    ->limit(20)
    ->offset(40)
    ->fetchAll();
```

### 5. SQL
You can view interpreted SQL query.

```php
use JQL\Enum\Operator;

$query->select('name, price, brand')
    ->from('data.products')
    ->where('brand.code', Operator::EQUAL, 'AD')
    ->and('name', Operator::NOT_EQUAL, 'Product B')
    ->or('name', Operator::EQUAL, 'Product B')
    ->or('price', Operator::GREATER_THAN_OR_EQUAL, 200)
    ->limit(2)
    ->offset(1);

echo $query->test();

// SELECT name, price, brand 
// FROM data.products 
// WHERE (
// 	 brand.code = 'AD' 
// 	AND name != 'Product B'
// ) OR (
// 	 name = 'Product B' 
// 	AND price >= 200
// ) 
// LIMIT 2
// OFFSET 1
```

## API

### Methods

### Enums

### Exceptions

### Traits

## Examples
