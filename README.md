# FiQueLa — File Query Language

> _[fi-kju-ela]_ · Query files like a database. No database required.

![Packagist Version](https://img.shields.io/packagist/v/1biot/fiquela)
[![CI](https://github.com/1biot/fiquela/actions/workflows/ci.yml/badge.svg)](https://github.com/1biot/fiquela/actions/workflows/ci.yml)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/1biot/fiquela/php)
![Packagist License](https://img.shields.io/packagist/l/1biot/fiquela)

![Coverage](https://img.shields.io/badge/coverage-90.22%25-lightgreen)
![PHPUnit Tests](https://img.shields.io/badge/PHPUnit-tests%3A_1286-lightgreen)
![PHPStan](https://img.shields.io/badge/phpstan-level_8-lightgreen)

**FiQueLa** brings SQL querying to structured files. Filter, join, group, aggregate, and export data from XML, CSV, JSON, NDJSON, YAML, NEON, XLSX, ODS, and HTTP access logs — using familiar SQL syntax or a fluent PHP API.

```sql
SELECT brand, COUNT(id) AS products, AVG(price) AS avg_price
FROM csv(catalog.csv, delimiter: ";").*
WHERE in_stock = "yes" AND price > 100
GROUP BY brand
HAVING products > 5
ORDER BY avg_price DESC
LIMIT 10
```

---

## Why FiQueLa?

- **No database setup** — query files directly, just PHP and Composer
- **Familiar SQL** — `SELECT`, `WHERE`, `JOIN`, `GROUP BY`, `HAVING`, `ORDER BY`, `UNION`, `INTO` and more
- **Cross-format joins** — join a CSV against an XML feed against a JSON file in one query
- **Stream-first** — large files are processed row by row with low memory overhead
- **Expression evaluator** — arithmetic, functions, and nested expressions everywhere

---

## Supported Formats

| Format          | Key        | Read | Write (INTO) |
|-----------------|------------|------|--------------|
| CSV             | `csv`      | ✅    | ✅            |
| XML             | `xml`      | ✅    | ✅            |
| JSON (stream)   | `jsonFile` | ✅    | ✅            |
| JSON            | `json`     | ✅    | ✅            |
| NDJSON          | `ndJson`   | ✅    | ✅            |
| XLSX            | `xls`      | ✅    | ✅            |
| ODS             | `ods`      | ✅    | ✅            |
| YAML            | `yaml`     | ✅    | —            |
| NEON            | `neon`     | ✅    | —            |
| HTTP access log | `log`      | ✅    | —            |
| Directory       | `dir`      | ✅    | —            |

---

## Installation

```bash
composer require 1biot/fiquela
```

Requires PHP 8.2+ with `ext-fileinfo`, `ext-json`,  `ext-mbstring`, `ext-xmlreader`, `ext-simplexml`, `ext-libxml` and
`ext-iconv`.

---

## Quick Start

**FQL string** — write queries just like SQL:

```php
use FQL\Query\Provider;

$results = Provider::fql("
    SELECT name, brand, ROUND(price, 2) AS price
    FROM xml(feed.xml).SHOP.SHOPITEM
    WHERE price > 100 AND in_stock = 'yes'
    ORDER BY price DESC
    LIMIT 20
")->execute()->fetchAll();
```

**Fluent API** — chain PHP methods:

```php
use FQL\Enum\Operator;
use FQL\Query\Provider;

$results = Provider::fromFileQuery('xml(feed.xml).SHOP.SHOPITEM')
    ->select('name', 'brand')
    ->round('price', 2)->as('price')
    ->where('price', Operator::GREATER_THAN, 100)
    ->and('in_stock', Operator::EQUAL, 'yes')
    ->orderBy('price')->desc()
    ->limit(20)
    ->execute()
    ->fetchAll();
```

---

## Core Features

### Joins — across files and formats

Join data from different files and formats in a single query. Left, right, inner, full outer, and subquery joins are all supported.

```sql
SELECT p.name, p.price, c.name AS category
FROM csv(products.csv).* AS p
LEFT JOIN json(categories.json).categories AS c
    ON p.category_id = c.id
WHERE p.price > 500
ORDER BY p.price DESC
```

```php
use \FQL\Enum\Operator;
use \FQL\Query\Provider;

$products   = Provider::fromFileQuery('csv(products.csv).*');
$categories = Provider::fromFileQuery('json(categories.json).categories');

$results = $products
    ->select('name', 'price')
    ->select('c.name')->as('category')
    ->leftJoin($categories, 'c')
        ->on('category_id', Operator::EQUAL, 'id')
    ->where('price', Operator::GREATER_THAN, 500)
    ->orderBy('price')->desc()
    ->execute();
```

### UNION — merge results from multiple sources

Combine results from different files, formats, or filter conditions. `UNION` deduplicates, `UNION ALL` keeps every row.

```sql
SELECT id, name, price, "warehouse_a" AS source
FROM csv(warehouse_a.csv).*
WHERE price < 100
UNION ALL
SELECT id, name, price, "warehouse_b" AS source
FROM xml(warehouse_b.xml).ITEMS.ITEM
WHERE price < 100
```

### Expression Evaluator

FiQueLa 3.0 evaluates arithmetic, function calls, and nested expressions anywhere — in `SELECT`, `WHERE`, `HAVING`, `ORDER BY`, and `ON` conditions.

```sql
-- arithmetic in SELECT
SELECT name, price * 1.21 AS price_with_vat, price * qty AS total

-- function call on left-hand side of WHERE
SELECT * FROM csv(users.csv).* WHERE LOWER(email) LIKE "%@example.com"

-- arithmetic in WHERE
SELECT * FROM csv(orders.csv).* WHERE price * (1 + vat_rate) > 1000

-- aggregate expression in HAVING
SELECT brand, SUM(price * qty) AS revenue
FROM csv(items.csv).*
GROUP BY brand
HAVING SUM(price * qty) > 50000
```

### Aggregation

Full `GROUP BY` with aggregate functions, `HAVING` filtering, and `DISTINCT` support.

```sql
SELECT
    category,
    COUNT(id) AS products,
    SUM(price) AS revenue,
    AVG(price) AS avg_price,
    MIN(price) AS cheapest,
    MAX(price) AS most_expensive,
    GROUP_CONCAT(DISTINCT name, " | ") AS product_list
FROM json(products.json).products
GROUP BY category
HAVING products > 10
ORDER BY revenue DESC
```

### Rich Filtering

```sql
-- type checking
WHERE price IS NUMBER AND tags IS ARRAY AND deleted_at IS NULL

-- pattern matching
WHERE name LIKE "%wireless%" AND sku REGEXP "^[A-Z]{2}-\d{4}$"

-- ranges and lists
WHERE price BETWEEN 100 AND 500
AND status IN ("active", "pending")

-- nested condition groups
WHERE price > 100
  AND (stock > 0 OR featured = true)
  AND (category = "electronics" OR discount > 0.2)
```

### Export with INTO

Write query results directly to a file. Directories are created automatically; existing files are never silently overwritten.

```sql
-- filter and export to a different format
SELECT name, price, brand
FROM xml(feed.xml).SHOP.SHOPITEM
WHERE price > 500
ORDER BY price DESC
INTO csv(exports/premium.csv)

-- convert between formats
SELECT * FROM csv(data.csv).* INTO json(data.json).root.items
SELECT * FROM json(data.json).root.items INTO xlsx(data.xlsx).Sheet1.A1
```

Supported output formats: **CSV**, **JSON**, **NDJSON**, **XML**, **XLSX**, **ODS**.

### Functions

**String:** `CONCAT`, `CONCAT_WS`, `UPPER`, `LOWER`, `SUBSTRING`, `REPLACE`, `LPAD`, `RPAD`, `EXPLODE`, `IMPLODE`, `LOCATE`, `REVERSE`, `MATCH AGAINST`

**Math:** `ROUND`, `CEIL`, `FLOOR`, `MOD`

**Utility:** `IF`, `CASE WHEN`, `COALESCE`, `NULLIF`, `CAST`, `DATE_FORMAT`, `NOW`, `CURDATE`, `RANDOM_STRING`, `BASE64_ENCODE`, `BASE64_DECODE`

**Aggregate:** `COUNT`, `SUM`, `AVG`, `MIN`, `MAX`, `GROUP_CONCAT` — all with optional `DISTINCT`

### EXPLAIN ANALYZE

Profile query execution without leaving PHP. Every pipeline phase reports row counts, wall time, and memory usage.

```sql
EXPLAIN ANALYZE
SELECT brand, COUNT(id) AS products, SUM(price) AS revenue
FROM csv(catalog.csv, delimiter: ";").*
GROUP BY brand
ORDER BY revenue DESC
```

```
| phase  | rows_in | rows_out | time_ms  | duration_pct | mem_peak_kb |
|--------|---------|----------|----------|--------------|-------------|
| stream | null    | 178 362  | 4 230.1  | 61%          | 14 231.5    |
| where  | 178 362 | 95 110   | 1 840.3  | 27%          | 14 231.5    |
| group  | 95 110  | 42       | 810.5    | 12%          | 18 540.2    |
| sort   | 42      | 42       | 2.1      | <1%          | 18 540.2    |
```

### HTTP Access Log Parsing

Query Nginx and Apache access logs with standard FQL — filter by status code, group by path, aggregate response times.

```sql
SELECT path, COUNT(*) AS hits, AVG(timeServeRequest) AS avg_ms
FROM log(access.log, "nginx_combined").*
WHERE status >= 400
GROUP BY path
ORDER BY hits DESC
LIMIT 20
```

Custom log formats via Apache `log_format` pattern:

```sql
FROM log(access.log, format: "custom", pattern: "%h %t %r %>s %D").*
```

---

## Two Query Styles

FiQueLa supports both styles interchangeably — pick whichever fits your workflow.

|                 | FQL string             | Fluent API              |
|-----------------|------------------------|-------------------------|
| Familiarity     | SQL developers         | PHP developers          |
| Dynamic queries | String interpolation   | Method chaining         |
| IDE support     | —                      | Autocomplete, types     |
| Readability     | High for complex joins | High for simple filters |

---

## Fetching Results

```php
$results = $query->execute();

// all rows as array
$rows = $results->fetchAll();

// first row only
$row = $results->fetch();

// single scalar value
$value = $results->fetchSingle('price');

// map to DTO
$dtos = $results->fetchAll(ProductDTO::class);

// stream row by row (low memory)
foreach ($results->getIterator() as $row) {
    // process $row
}
```

---

## Ecosystem

| Project                                                 | Description                                                              |
|---------------------------------------------------------|--------------------------------------------------------------------------|
| [**FiQueLa CLI**](https://github.com/1biot/fiquela-cli) | Interactive REPL and command-line querying with paginated table output   |
| [**FiQueLa API**](https://github.com/1biot/fiquela-api) | RESTful server with JWT auth, file management, query history, and export |
| [**FiQueLa Studio**](https://studio.fiquela.io)         | Web-based visual query explorer — connect to any FiQueLa API instance    |

```bash
# Install CLI
curl -fsSL https://raw.githubusercontent.com/1biot/fiquela-cli/main/install.sh | bash

# Interactive REPL
fiquela-cli --file=data.csv

# Single query
fiquela-cli "SELECT name, price FROM csv(data.csv).* WHERE price > 100;"
```

[![Deploy to DigitalOcean](https://www.deploytodo.com/do-btn-blue.svg)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/1biot/fiquela-api/tree/main?refcode=92025543cb9f)

---

## Known Limitations

- `JOIN` and `ORDER BY` load data into memory — plan accordingly for very large datasets
- `INTO` throws `FileAlreadyExistsException` if the target file already exists

---

## Roadmap

- [ ] MessagePack format support
- [ ] Parquet format support
- [ ] Redis / APCu hashmap cache for JOIN
- [ ] LSP server for `.fql` files (PhpStorm, VS Code)

---

## Documentation

Full documentation at **[docs.fiquela.io](https://docs.fiquela.io)**

- [Quickstart](https://docs.fiquela.io/quickstart)
- [FQL Syntax](https://docs.fiquela.io/querying/fql-syntax)
- [Fluent API](https://docs.fiquela.io/querying/fluent-api)
- [Joins](https://docs.fiquela.io/querying/joins)
- [Conditions](https://docs.fiquela.io/querying/conditions)
- [Functions](https://docs.fiquela.io/functions/string-functions)
- [EXPLAIN ANALYZE](https://docs.fiquela.io/advanced/explain-analyze)
- [Export with INTO](https://docs.fiquela.io/advanced/export-into)
- [API Reference](docs/api-reference.md)

---

## Contributing

Contributions are welcome! Fork the repo, create a branch, make your changes, and open a pull request. All tests must pass.

```bash
composer install
composer test       # PHP CodeSniffer, PHPStan level 8, PHPUnit
composer examples   # run example queries
```
