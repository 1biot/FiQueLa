# JQL - JSON Query Language
JQL (JSON Query Language) is a PHP library for easy manipulation of JSON data. It offers SQL-inspired syntax for querying, filtering, and aggregating data. The library is designed with a focus on modularity, simplicity, and efficiency.

[![Latest Stable Version](http://poser.pugx.org/1biot/jql/v)](https://packagist.org/packages/1biot/jql)
[![Total Downloads](http://poser.pugx.org/1biot/jql/downloads)](https://packagist.org/packages/1biot/jql)
[![Latest Unstable Version](http://poser.pugx.org/1biot/jql/v/unstable)](https://packagist.org/packages/1biot/jql)
[![License](http://poser.pugx.org/1biot/jql/license)](https://packagist.org/packages/1biot/jql)
[![PHP Version Require](http://poser.pugx.org/1biot/jql/require/php)](https://packagist.org/packages/1biot/jql)

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
use JQL\Query;

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

## API

### Methods

### Enums

### Exceptions

### Traits

## Examples
