# FQL: File Query Language

File Query Language (FQL) is a SQL-like syntax for querying data from files. It is a simple and powerful way to query data

**table of contents**:

* _1_ - [Interpreted SQL](#1-interpreted-fql)
* _2_ - [File Query](#2-file-query)
* _3_ - [Select and Alias Fields](#3-select-and-alias-fields)
* _4_ - [Functions](#4-functions)
* _5_ - [Joining Data Sources](#5-joining-data-sources)
* _6_ - [Conditions](#6-conditions)
* _7_ - [Grouping and Aggregations](#7-grouping-and-aggregations)
* _8_ - [Sorting and Filtering](#8-sorting-and-filtering)
* _9_ - [Pagination and Limits](#9-pagination-and-limits)
* _10_ - [Explain](#10-explain)
* _11_ - [Union](#11-union)
* _12_ - [Into](#12-into)
* _13_ - [Describe](#13-describe)

## 1. Interpreted FQL

All [Fluent API](fluent-api.md) queries can be converted into SQL like syntax called File Query Language (FQL).

Every query implements `\Stringable` interface and can be cast to the string. This feature is particularly useful for
debugging and gaining a clear understanding of how queries are constructed and executed.

```php
use FQL\Enum\Operator;
use FQL\Query

$query = Query\Provider::fromFileQuery('jsonFile(./examples/data/products.tmp).data.products')
    ->select('brand.code')->as('brandCode')
    ->groupConcat('id', '/')->as('products')
    ->sum('price')->as('totalPrice')
    ->count('productId')->as('productCount')
    ->where('price', Operator::LESS_THAN, 300)
    ->or('price', Operator::GREATER_THAN, 400)
    ->groupBy('brand.code')
    ->orderBy('productCount')->desc();

echo (string) $query;
```

Output:

```sql
SELECT
  brand.code AS brandCode,
  GROUP_CONCAT(id, "/") AS products,
  SUM(price) AS totalPrice,
  COUNT(productId) AS productCount
FROM jsonFile(products.tmp).data.products
WHERE
  price < 200
  OR price > 300
GROUP BY brand.code
ORDER BY productCount DESC
```

## 2. File Query

File Query is syntax allowing you to load file in FQL string directly without using any file provider.
It is usable for `FROM`, `JOIN` and `INTO` clause. File Query consists of three main parts:

- `format` is the format name written directly before the parenthesis (e.g. `csv`, `json`, `xml`, `jsonFile`, `log`). If omitted, the format is detected from the file extension automatically.
- `pathToFile` is the first argument inside the parenthesis — a relative or absolute path to the file.
- `params` are optional additional arguments after the file path, separated by commas. Arguments can be positional (`"value"`) or named (`key: "value"`), but you cannot mix positional and named arguments in the same query.
- `path.to.data` is a dotted path to the data in the file.

----

* **syntax**: `format(pathToFile[, params]).path.to.data`
* **regexp**: `/(?<fq>(?<fs>(?<t>[a-zA-Z]{2,8})\((?<p>[\w\s.\-\/]+(?:\.\w{2,5})?)(?<a>(?:,\s*(?:\w+\s*:\s*"[^"]*"|"[^"]*"))*)\))?(?<q>\*|\.*[\w*.\-\_]{1,})?)/`
  * _**\<t\>**_: `[a-zA-Z]{2,8}` - file format name
  * _**\<p\>**_: `[\w\s.\-\/]+(?:\.\w{2,5})?` - path to the file, relative or absolute
  * _**\<a\>**_: `(?:,\s*(?:\w+\s*:\s*"[^"]*"|"[^"]*"))*` - optional arguments (positional or named)
  * _**\<q\>**_: `\*|\.*[\w*.\-\_]{1,}` - path to the data in the file

**Parameter defaults:**

| Format | Parameter   | Default          |
|--------|-------------|------------------|
| CSV    | encoding    | `utf-8`          |
| CSV    | delimiter   | `,`              |
| XML    | encoding    | `utf-8`          |
| LOG    | format      | `nginx_combined` |

**Example:**

```php
use FQL\Query;

$query = Query\Provider::fql(<<<SQL
SELECT
  brand.code AS brandCode,
  GROUP_CONCAT(id, "/") AS products,
  SUM(price) AS totalPrice,
  COUNT(productId) AS productCount
FROM csv(./examples/data/products.tmp, "utf-8", ";").data.products
SQL
);
```

## 3. Select and Alias Fields

```sql
SELECT
    [DISTINCT]
    select_expr [AS select_alias] [, select_expr [AS select_alias]] ...
    [EXCLUDE excl_expr [, excl_expr] ...]
```

- _**select_expr**_: is a column name, function or user string. Supports dot notation for nested fields. `*` can be
  combined with additional fields, for example `SELECT *, totalPrice`. Aliased wildcard `alias.*` selects all fields from an aliased source (e.g. `SELECT p.*` when `FROM ... AS p`).
- _**select_alias**_: is an alias for the _**select_expr**_.
- _**excl_alias**_: is an aliased column name to exclude from the result set. Supports dot notation for nested fields.

### Path syntax for nested / escaped / array fields

Column references support three kinds of segments joined by `.`:

1. **Plain identifiers** — `info.orderID`, `brand.code.full`.
2. **Backtick-escaped segments** when a key contains spaces, diacritics, a dot or a bracket (e.g. a column literally named `Název Zboží.cz`). Individual segments or a whole chain may be escaped:
   ```sql
   SELECT `Název Zboží.cz` AS nazev
   SELECT `info`.`orderID` AS id
   SELECT `info`.date                     -- mixing quoted and plain segments is fine
   ```
3. **Array iteration** using `[]` after any segment. Returns all items of the underlying array, suitable for aggregate inputs or passing to length-style functions:
   ```sql
   SELECT products.product[] AS items,
          LENGTH(`products`.`product`[]) AS itemCount
   ```

Aliases in `AS` clauses follow the same rule — wrap them in backticks when they contain non-identifier characters (`` AS `Kód objednávky` ``). The tokenizer strips the outer backticks so the alias appears verbatim in the result set.

**Example:**

```sql
SELECT
  brand.code AS brandCode,
  GROUP_CONCAT(id, "/") AS products,
  SUM(price) AS totalPrice,
  COUNT(productId) AS productCount
EXCLUDE productCount
FROM jsonFile(./examples/data/products.tmp).data.products
HAVING productCount > 1
```

**Result:**

In this case `productCount` is excluded from the result set but condition is still applied.

```
+-----------+----------------------+------------+
| brandCode | products             | totalPrice |
+-----------+----------------------+------------+
| Brand A   | 1/2/3/4/5/6/7/8/9/10 | 1000       |
+-----------+----------------------+------------+
```

## 4. Functions

```sql
SELECT
  function_expr(expr, ...) [AS alias] ...
```

- _**function_expr**_: a function name with arguments is subject to a regular expression:
```regexp
/\b(?!_)[A-Z0-9_]{2,}(?<!_)\(.*?\)/i
```

### String functions

| Function                  | Description                            |
|---------------------------|----------------------------------------|
| `CONCAT`                  | Concatenate values with no separator.  |
| `CONCAT_WS`               | Concatenate values with separator.     |
| `LOWER`                   | Convert string to lower case.          |
| `UPPER`                   | Convert string to upper case.          |
| `REVERSE`                 | Reverse string.                        |
| `EXPLODE`                 | Split string to array.                 |
| `IMPLODE`                 | Join array to string.                  |
| `BASE64_ENCODE`           | Decode base64 string.                  |
| `BASE64_DECODE`           | Encode string to base64.               |
| `RANDOM_STRING`           | Generates random string.               |
| `MATCH(...) AGAINST(...)` | Simple fulltext score matching         |
| `LPAD`                    | Left pad string with character.        |
| `RPAD`                    | Right pad string with character.       |
| `SUBSTRING`, `SUBSTR`     | Extract substring from string.         |
| `LOCATE`                  | Find position of substring in string.  |
| `REPLACE`                 | Replace all occurrences of a substring |

**Examples:**

```sql
SELECT
    CONCAT('Hello', ' ', 'World') AS greeting,
    LOWER('Hello World') AS lower,
    UPPER('Hello World') AS upper,
    REVERSE('Hello World') AS reverse,
    EXPLODE('Hello World', ' ') AS explode,
    IMPLODE(explode, ' ') AS implode,
    BASE64_ENCODE('SGVsbG8gV29ybGQ=') AS fromBase64,
    BASE64_DECODE('Hello World') AS toBase64,
    RANDOM_STRING(10) AS randomString,
    LPAD('Hello', 10, '-') AS lpad,
    RPAD('Hello', 10, '-') AS rpad
    MATCH(name, description) AGAINST('Hello World' IN NATURAL MODE) AS _score
    SUBSTRING('Hello World', 1, 5) AS substring,
    SUBSTR('Hello World', 1, 5) AS substr,
    LOCATE('World', 'Hello World') AS locate,
    REPLACE('SQL Tutorial', 'SQL', 'HTML') AS replace
FROM json(./examples/data/products.tmp).data.products
```

#### Fulltext search

```sql
MATCH(field[, field ...]) AGAINST('search_query' [IN [NATURAL | BOOLEAN] MODE])
```

Fulltext search is a special function for searching in text fields. It uses the `MATCH` and `AGAINST` functions.
Supports two modes: `NATURAL` and `BOOLEAN`. Result is a score of the match, and you can use it for filtering and sorting.

```sql
SELECT
    id,
    name,
    description,
    MATCH(name, description) AGAINST('Hello World' IN NATURAL MODE) AS _score
FROM json(./examples/data/products.tmp).data.products
HAVING _score > 0.5
ORDER BY _score DESC
```

### Utils functions

| Function            | Description                                                            |
|---------------------|------------------------------------------------------------------------|
| `ARRAY_COMBINE`     | Combine two array with keys and array with values into a single array  |
| `ARRAY_FILTER`      | Filter array from empty values                                         |
| `ARRAY_MERGE`       | Merge two arrays into a single array                                   |
| `ARRAY_SEARCH`      | Search the key for needle in the array                                 |
| `COL_SPLIT`         | Split array field into columns (optional format + key field)           |
| `CAST`              | Cast value to the requested type                                       |
| `COALESCE`          | Coalesce values (first non-null value)                                 |
| `COALESCE_NE`       | Coalesce values when not empty (first non-empty value)                 |
| `DATE_FORMAT`       | Format date field to string                                            |
| `FROM_UNIXTIME`     | Convert unix timestamp to date/time                                    |
| `STR_TO_DATE`       | Parse string to date or time                                           |
| `DATE_DIFF`         | Calculate difference between two dates                                 |
| `DATE_ADD`          | Add interval to date                                                   |
| `DATE_SUB`          | Subtract interval from date                                            |
| `YEAR`              | Get year from date                                                     |
| `MONTH`             | Get month from date                                                    |
| `DAY`               | Get day of month from date                                             |
| `NOW`               | Get current date and time                                              |
| `CURRENT_TIMESTAMP` | Get current unix timestamp                                             |
| `CURDATE`           | Get current date                                                       |
| `CURTIME`           | Get current time                                                       |
| `LENGTH`            | Get length of value. Recognizes arrays as count, null as 0 and strings |
| `RANDOM_BYTES`      | Generates cryptographically secure random bytes.                       |
| `UUID`              | Generates a random UUID v4.                                            |
| `IF`                | If condition is true, return first value, otherwise second value.      |
| `IFNULL`            | If value is null, return second value, otherwise first value.          |
| `ISNULL`            | Check if value is null.                                                |
| `CASE`              | Case statement for conditional logic.                                  |

**Examples:**

```sql
SELECT
    ARRAY_COMBINE(filedWitArrayKeys, fieldWithArrayValues) AS arrayCombine,
    ARRAY_MERGE(fieldWithArray1, fieldWithArray2) AS arrayMerge,
    ARRAY_FILTER(fieldWithArray) AS arrayFilter,
    ARRAY_SEARCH(fieldWithArray, "needle") AS arraySearch,
    COL_SPLIT(items, "item_%index", "id") AS itemColumns,
    CAST(price AS DOUBLE) AS castPrice,
    COALESCE(NULL, 'Hello World') AS coalesce,
    COALESCE_NE(0, 'Hello World') AS coalesceNe,
    DATE_FORMAT(dateField, 'Y-m-d') AS dateFormat,
    FROM_UNIXTIME(unixTimestamp, 'Y-m-d H:i:s') AS fromUnixTime,
    STR_TO_DATE(dateString, '%Y-%m-%d') AS strToDate,
    DATE_DIFF(dateField1, dateField2) AS dateDiff,
    DATE_ADD(dateField, '+1 day') AS dateAdd,
    DATE_SUB(dateField, '+1 day') AS dateSub,
    YEAR(dateField) AS year,
    MONTH(dateField) AS month,
    DAY(dateField) AS day,
    NOW() AS now,
    CURRENT_TIMESTAMP() AS currentTimestamp,
    CURDATE() AS curdate,
    CURTIME() AS curtime,
    LENGTH(filedWitArrayKeys) AS keysCount,
    LENGTH('Hello World') AS stringLength,
    RANDOM_BYTES(16) AS randomBytes,
    UUID() AS uuid,
    CASE
        WHEN stock > 100 THEN 'more than 100'
        WHEN stock > 50 THEN 'more than 50'
        WHEN stock > 0 THEN 'last stock items'
        ELSE 'out of stock'
    END AS caseResult,
    IF(condition, result1, result2) AS ifResult,
    IFNULL(field, result) AS ifNull,
    ISNULL(field) AS isNull
FROM jsonFile(./examples/data/products.tmp).data.products
```

### Hashing functions

| Function | Description                 |
|----------|-----------------------------|
| `MD5`    | MD5 algorithm for hashing   |
| `SHA1`   | SHA1 algorithm for hashing  |

**Examples:**

```sql
SELECT
  MD5('Hello World') AS md5,
  SHA1('Hello World') AS sha1
FROM jsonFile(./examples/data/products.tmp).data.products
```

### Math functions

| Function   | Description                            |
|------------|----------------------------------------|
| `CEIL`     | Round number up                        |
| `FLOOR`    | Round number down                      |
| `MOD`      | Modulo operation                       |
| `ROUND`    | Round number mathematically            |
| `ADD`      | Add numbers or fields (variadic)       |
| `SUB`      | Subtract numbers or fields (variadic)  |
| `MULTIPLY` | Multiply numbers or fields (variadic)  |
| `DIVIDE`   | Divide numbers or fields (variadic)    |

**Example:**

```sql
SELECT
  CEIL(3.14) AS ceil,
  FLOOR(3.14) AS floor,
  MOD(10, 3) AS mod,
  ROUND(3.14) AS round,
  ADD(1, 2, 3, 4) AS add,
  SUB(10, 2, 3) AS sub,
  MULTIPLY(2, 3, 4) AS multiply,
  DIVIDE(10, 2, 5) AS divide
FROM jsonFile(./examples/data/products.tmp).data.products
```

## 5. Joining Data Sources

Use `JOIN` to join data sources in your query. You can join multiple data sources in a single query. When you are joining
data sources, you must specify alias `AS` and `ON` condition. Multiple using of `ON` statement rewrites last condition.

```sql
FROM file_reference [AS from_alias]
[
    {[INNER] JOIN | {LEFT|RIGHT|FULL} [OUTER] JOIN}
    {file_reference | (subquery)}
    AS alias_reference
    ON where_condition
]
```

- _**file_reference**_: is a [FileQuery](#2-file-query).
- _**subquery**_: a nested SELECT statement in parentheses, e.g. `(SELECT id, name FROM ... WHERE ...)`.
- _**from_alias**_: optional alias for the FROM source. When set, fields can be accessed as `from_alias.field_name` and `from_alias.*` selects all fields.

| Join type | Description      |
|-----------|------------------|
| `INNER`   | Inner join       |
| `LEFT`    | Left outer join  |
| `RIGHT`   | Right outer join |
| `FULL`    | Full outer join  |

**Example with FROM alias:**

```sql
SELECT
    u.name,
    o.id AS orderId,
    o.total_price AS totalPrice
FROM json(./examples/data/users.json).data.users AS u
LEFT JOIN
    xml(./examples/data/orders.xml).orders.order AS o
        ON u.id = o.user_id
```

**Example with aliased wildcard:**

```sql
SELECT
    u.*,
    o.total_price AS totalPrice
FROM json(./examples/data/users.json).data.users AS u
LEFT JOIN
    xml(./examples/data/orders.xml).orders.order AS o
        ON u.id = o.user_id
```

**Example with subquery JOIN:**

```sql
SELECT
    u.name,
    o.id AS orderId,
    o.total_price AS totalPrice
FROM json(./examples/data/users.json).data.users AS u
LEFT JOIN
    (SELECT id, user_id, total_price FROM xml(./examples/data/orders.xml).orders.order WHERE total_price > 100) AS o
        ON u.id = o.user_id
ORDER BY o.total_price DESC
```

## 6. Conditions

```sql
logical_operator:
    AND | OR | XOR

compare_type_value:
    BOOLEAN | TRUE | FALSE | NUMBER | INT | DOUBLE | STRING | NULL | ARRAY | OBJECT

value_type:
    string | float | int | array | bool
    string | float | int | array | bool

where_condition:
    expr OR expr
  | expr XOR expr
  | expr AND expr

expr:
    field_expr comparison_operator value_expr

field_expr:
    column_name | alias_column_name | array_nested_field
                
comparison_operator: = | >= | > | <= | < | <> | != | !== | == | LIKE | NOT LIKE | IS | IS NOT | IN | NOT IN | 
    BETWEEN | NOT BETWEEN | REGEXP | NOT REGEXP

value_expr:
    value_type | compare_type_value

[[WHERE | HAVING]
    where_condition
    [logical_operator where_condition]]
```

- _**field_expr**_: is a column name
- _**value_expr**_: is a value comparable value.
- _**where_condition**_: is a condition for filtering data.
- _**logical_operator**_: is a logical operator `AND`, `OR`, `XOR`, `NOT`.
- _**comparison_operator**_: is an operator for comparing values.
- _**expr**_: is a combination of _**field_expr**_, _**comparison_operator**_ and _**value_expr**_.

> ~~⚠️ **FQL** still does not support parentheses for conditions.~~

> 📢 **FQL** Now supports parentheses for conditions !!! 🎉

**Example:**

```sql
SELECT
    id,
    name,
    o.id AS orderId,
    o.total_price AS totalPrice
FROM json(./examples/data/users.json).data.users
WHERE
    id = 1
    AND name = 'John Doe'
    OR name = 'Jane Doe'
```

**REGEXP example:**

```sql
SELECT
    id,
    name
FROM json(./examples/data/products.tmp).data.products
WHERE
    name REGEXP "^Product [A-B]$"
```

## 7. Grouping and Aggregations

```sql
[GROUP BY group_by_expr [, group_by_expr] ...]
[HAVING where_condition]
```

- _**group_by_expr**_: is a column name or alias column name.
- _**where_condition**_: more info [here](#6-conditions).

### Aggregations

| Function        | Description        |
|-----------------|--------------------|
| `COUNT`         | Count rows         |
| `SUM`           | Sum values         |
| `AVG`           | Average values     |
| `MIN`           | Minimum value      |
| `MAX`           | Maximum value      |
| `GROUP_CONCAT`  | Concatenate values |

Aggregate functions support `DISTINCT` in the same way as SQL, for example `COUNT(DISTINCT id)`.

**Example:**

```sql
SELECT
    brand.code AS brandCode,
    GROUP_CONCAT(DISTINCT id, "/") AS products,
    SUM(DISTINCT price) AS totalPrice,
    COUNT(DISTINCT productId) AS productCount,
    AVG(price) AS avgPrice,
    MIN(DISTINCT price) AS minPrice,
    MAX(DISTINCT price) AS maxPrice
FROM jsonFile(./examples/data/products.tmp).data.products
GROUP BY brand.code
HAVING
    productCount > 10
    OR totalPrice > 1000
    OR maxPrice < 500
```

## 8. Sorting and Filtering

```sql
order_type_expr:
    [ASC | DESC ]

[ORDER BY order_expr order_type_expr [, order_expr order_type_expr ...]]
```

- _**order_expr**_: is a column name or alias column name.

| Sorting type  | Description  |
|---------------|--------------|
| `ASC`         | Ascending    |
| `DESC`        | Descending   |

**Example:**

```sql
SELECT
    brand.code AS brandCode,
    RANDOM_STRING(16) AS randomString
    GROUP_CONCAT(id, "/") AS products,
    SUM(price) AS totalPrice,
    COUNT(productId) AS productCount
FROM jsonFile(./examples/data/products.tmp).data.products
ORDER BY
    productCount DESC,
    totalPrice ASC
```

## 9. Pagination and Limits

```sql
[LIMIT {row_count [,offset]  | row_count OFFSET offset}]
```

- _**row_count**_: is a number of rows to return.
- _**offset**_: is a number of rows to skip.

**Example:**

```sql
SELECT
    brand.code AS brandCode,
    GROUP_CONCAT(id, "/") AS products,
    SUM(price) AS totalPrice,
    COUNT(productId) AS productCount
FROM jsonFile(./examples/data/products.tmp).data.products
LIMIT 10
OFFSET 5
```

## 10. Explain

Use `EXPLAIN` to get a flat, human-friendly execution plan. Use `EXPLAIN ANALYZE` to execute the query and collect
real row counts and timings. The result is always a simple table and suitable for display.

Columns:
- `phase` — pipeline stage name (`stream`, `join`, `where`, `group`, `having`, `sort`, `limit`, `union`)
- `rows_in` — number of rows entering the phase (`null` for plan-only)
- `rows_out` — number of rows leaving the phase (`null` for plan-only)
- `filtered` — rows removed (`rows_in - rows_out`, `null` for plan-only)
- `time_ms` — wall-clock time in milliseconds (`null` for plan-only)
- `duration_pct` — percentage of total query time (`null` for plan-only)
- `mem_peak_kb` — peak memory usage in KB at end of phase (`null` for plan-only)
- `note` — human-readable description of the phase configuration

**Example (plan only):**

```sql
EXPLAIN
SELECT
    id,
    name
FROM json(./examples/data/products.tmp).data.products
WHERE price > 100
ORDER BY name DESC
LIMIT 10
```

**Example with analysis:**

```sql
EXPLAIN ANALYZE
SELECT
    id,
    name
FROM json(./examples/data/products.tmp).data.products
WHERE price > 100
ORDER BY name DESC
LIMIT 10
```

### Union sub-phases

When a query includes `UNION` or `UNION ALL`, the explain output includes union phases:

- **Single union**: phases are prefixed with `union_` (e.g. `union_stream`, `union_where`) followed by a summary row `union`.
- **Multiple unions**: phases are indexed (e.g. `union_1_stream`, `union_1_where`, `union_2_stream`) with summary rows `union_1`, `union_2`.

```sql
EXPLAIN ANALYZE
SELECT id FROM json(./examples/data/products.json).data.products
WHERE price > 100
UNION ALL
SELECT id FROM json(./examples/data/products.json).data.products
WHERE price > 200
```

## 11. Union

Use `UNION` to combine results from multiple queries, removing duplicate rows. Use `UNION ALL` to combine results
keeping all rows including duplicates. The `UNION` clause is placed after all other clauses of each query.

```sql
select_statement
UNION [ALL]
select_statement
[UNION [ALL]
select_statement ...]
```

The number of selected columns must match across all combined queries.

**Example:**

```sql
SELECT name, price FROM json(./examples/data/products.json).data.products
WHERE price <= 100
UNION
SELECT name, price FROM json(./examples/data/products.json).data.products
WHERE price >= 400
```

**Example with UNION ALL:**

```sql
SELECT name, price FROM json(./examples/data/products.json).data.products
WHERE price >= 300
UNION ALL
SELECT name, price FROM json(./examples/data/products.json).data.products
WHERE price >= 300
```

**Chaining multiple unions:**

```sql
SELECT name, price FROM xml(./examples/data/feed1.xml).SHOP.ITEM
WHERE price > 100
UNION
SELECT name, price FROM xml(./examples/data/feed2.xml).SHOP.ITEM
WHERE price > 200
UNION ALL
SELECT name, price FROM xml(./examples/data/feed3.xml).SHOP.ITEM
```

## 12. Into

Use `INTO` to export query results into a file.

```sql
SELECT name, price
FROM csv(./examples/data/products-utf-8.csv).*
INTO csv(./exports/products.csv)
```

`INTO` uses the same file query syntax as `FROM`:

```text
format(pathToFile[, params]).query
```

Interpretation of `.query` depends on output format:

| Format   | `.query` meaning                          | Example      |
|----------|-------------------------------------------|--------------|
| XML      | `ROOT.ROW` (root element + row element)   | `.SHOP.ITEM` |
| JSON     | nested key path for resulting array       | `.root.items` |
| XLSX/ODS | `SheetName.StartCell`                     | `.Sheet1.B4` |
| CSV      | ignored                                   | —            |
| NDJSON   | ignored                                   | —            |

Notes:

- Export is performed by result provider method `->execute()->into(...)`.
- Existing target files are not overwritten (`FileAlreadyExistsException`).
- Missing output directories are created recursively.

## 13. Describe

Use `DESCRIBE` to inspect the schema of a data source. Returns one row per column with type statistics.

```sql
DESCRIBE file_reference
```

- _**file_reference**_: is a [FileQuery](#2-file-query).

`DESCRIBE` is a standalone statement — it cannot be combined with `SELECT`, `WHERE`, `GROUP BY`, `ORDER BY`, or `LIMIT`.

**Example:**

```sql
DESCRIBE json(./examples/data/products.json).data.products
```

**Example with CSV:**

```sql
DESCRIBE csv(./examples/data/products.csv).*
```

### Output columns

| Column         | Type     | Description                                                    |
|----------------|----------|----------------------------------------------------------------|
| `column`       | string   | Column name (dot notation for nested objects)                  |
| `types`        | array    | Map of type name to occurrence count                           |
| `totalRows`    | int      | Number of non-empty rows for this column                       |
| `totalTypes`   | int      | Number of distinct types observed                              |
| `dominant`     | string   | Most frequent type                                             |
| `suspicious`   | bool     | `true` if column has mixed non-empty types (except int+double) |
| `confidence`   | float    | Ratio of dominant type occurrences to total (0.0–1.0)          |
| `completeness` | float    | Ratio of non-empty rows to total rows (0.0–1.0)               |
| `constant`     | bool     | `true` if all non-empty values are identical                   |
| `isEnum`       | bool     | `true` if column has 2–5 unique values                         |
| `isUnique`     | bool     | `true` if all non-empty values are unique                      |

## Next steps
- [Opening Files](opening-files.md)
- [Fluent API](fluent-api.md)
- File Query Language
- [Fetching Data](fetching-data.md)
- [Query Life Cycle](query-life-cycle.md)
- [FiQueLa CLI](fiquela-cli.md)
- [Query Inspection and Benchmarking](query-inspection-and-benchmarking.md)
- [API Reference](api-reference.md)

or go back to [README.md](../README.md).
