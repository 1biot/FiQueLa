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

## 1. Interpreted FQL

All [Fluent API](fluent-api.md) queries can be converted into SQL like syntax called File Query Language (FQL).

Every query implements `\Stringable` interface and can be cast to the string. This feature is particularly useful for
debugging and gaining a clear understanding of how queries are constructed and executed.

```php
use FQL\Enum\Operator;
use FQL\Query

$query = Query\Provider::fromFileQuery('[jsonFile](./examples/data/products.tmp).data.products')
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
FROM [jsonFile](products.tmp).data.products
WHERE
  price < 200
  OR price > 300
GROUP BY brand.code
ORDER BY productCount DESC
```

## 2. File Query

File Query is syntax allowing you to load file in FQL string directly without using any file provider.
It is usable for `FROM` and `JOIN` clause. File Query consists of five parts:

- `format` is using for selecting concrete file format otherwise, it tries to find out from the file extension automatically.
- `pathToFile` is a relative or absolute path to the file.
- `encoding` is an optional parameter for specifying the encoding of the file.
- `delimiter` is an optional parameter for specifying the delimiter of the file.
- `path.to.data` is a doted path to the data in the file.

----

* **syntax**: `[format](pathToFile).path.to.data`
* **regexp**: `/(?<fq>(?<filePart>(\[(?<format>[a-zA-Z]{2,8})])?(\((?<pathToFile>[\w\s\.\-\/]+(\.\w{2,5})?)(?<a>(,\s*(?<encoding>[a-zA-Z0-9\-]+))(,\s*([\'"])(?<delimiter>.)\12)?)?\)))?(?<queryPart>^\*|\.*[\w*\.\-\_]{1,})?)/`
  * _**\<filePart\>**_: part for file reference
  * _**\<format\>**_: `[a-zA-Z]{2,8}` - file format extension
  * _**\<pathToFile\>**_: `[\w\s\.\-\/]+(\.\w{2,5})?` - path to the file, relative or absolute
  * _**\<encoding\>**_: `[a-zA-Z0-9\-]+` - encoding of the file
  * _**\<delimiter\>**_: `.` - encoding of the file
  * _**\<queryPart\>**_: `^\*|\.*[\w*\.\-\_]{1,}` - path to the data in the file

**Example:**

```php
use FQL\Query;

$query = Query\Provider::fql(<<<SQL
SELECT
  brand.code AS brandCode,
  GROUP_CONCAT(id, "/") AS products,
  SUM(price) AS totalPrice,
  COUNT(productId) AS productCount
FROM [csv](./examples/data/products.tmp, utf-8, ";").data.products
SQL
);
```

## 3. Select and Alias Fields

```sql
SELECT
    [DISTINCT]
    select_expr [AS select_alias |, select_expr ] ...
    [EXCLUDE excl_expr [, excl_expr] ...]
```

- _**select_expr**_: is a column name, function or user string. Supports dot notation for nested fields.
- _**select_alias**_: is an alias for the _**select_expr**_.
- _**excl_alias**_: is an aliased column name to exclude from the result set. Supports dot notation for nested fields.

**Example:**

```sql
SELECT
  brand.code AS brandCode,
  GROUP_CONCAT(id, "/") AS products,
  SUM(price) AS totalPrice,
  COUNT(productId) AS productCount
EXCLUDE productCount
FROM [jsonFile](./examples/data/products.tmp).data.products
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

| Function                  | Description                           |
|---------------------------|---------------------------------------|
| `CONCAT`                  | Concatenate values with no separator. |
| `CONCAT_WS`               | Concatenate values with separator.    |
| `LOWER`                   | Convert string to lower case.         |
| `UPPER`                   | Convert string to upper case.         |
| `REVERSE`                 | Reverse string.                       |
| `EXPLODE`                 | Split string to array.                |
| `IMPLODE`                 | Join array to string.                 |
| `BASE64_ENCODE`           | Decode base64 string.                 |
| `BASE64_DECODE`           | Encode string to base64.              |
| `RANDOM_STRING`           | Generates random string.              |
| `MATCH(...) AGAINST(...)` | Simple fulltext score matching        |

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
    MATCH(name, description) AGAINST('Hello World' IN NATURAL MODE) AS _score
FROM [json](./examples/data/products.tmp).data.products
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
FROM [json](./examples/data/products.tmp).data.products
HAVING _score > 0.5
ORDER BY _score DESC
```

### Utils functions

| Function        | Description                                                            |
|-----------------|------------------------------------------------------------------------|
| `ARRAY_COMBINE` | Combine two array with keys and array with values into a single array  |
| `ARRAY_FILTER`  | Filter array from empty values                                         |
| `ARRAY_MERGE`   | Merge two arrays into a single array                                   |
| `COALESCE`      | Coalesce values (first non-null value)                                 |
| `COALESCE_NE`   | Coalesce values when not empty (first non-empty value)                 |
| `FORMAT_DATE`   | Format date field to string                                            |
| `LENGTH`        | Get length of value. Recognizes arrays as count, null as 0 and strings |
| `RANDOM_BYTES`  | Generates cryptographically secure random bytes.                       |
| `IF`            | If condition is true, return first value, otherwise second value.      |
| `IFNULL`        | If value is null, return second value, otherwise first value.          |
| `ISNULL`        | Check if value is null.                                                |
| `CASE`          | Case statement for conditional logic.                                  |

**Examples:**

```sql
SELECT
    ARRAY_COMBINE(filedWitArrayKeys, fieldWithArrayValues) AS arrayCombine,
    ARRAY_MERGE(fieldWithArray1, fieldWithArray2) AS arrayMerge,
    COALESCE(NULL, 'Hello World') AS coalesce,
    COALESCE_NE(0, 'Hello World') AS coalesceNe,
    FORMAT_DATE(dateField, 'Y-m-d') AS dateFormat,
    LENGTH(filedWitArrayKeys) AS keysCount,
    LENGTH('Hello World') AS stringLength,
    RANDOM_BYTES(16) AS randomBytes,
    CASE 
        WHEN stock > 100 THEN 'more than 100'
        WHEN stock > 50 THEN 'more than 50'
        WHEN stock > 0 THEN 'last stock items'
        ELSE 'out of stock'
    END AS caseResult,
    IF(condition, result1, result2) AS ifResult,
    IFNULL(field, result) AS ifNull,
    ISNULL(field) AS isNull
FROM [jsonFile](./examples/data/products.tmp).data.products
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
FROM [jsonFile](./examples/data/products.tmp).data.products
```

### Math functions

| Function | Description                 |
|----------|-----------------------------|
| `CEIL`   | Round number up             |
| `FLOOR`  | Round number down           |
| `MOD`    | Modulo operation            |
| `ROUND`  | Round number mathematically |

**Example:**

```sql
SELECT
  CEIL(3.14) AS ceil,
  FLOOR(3.14) AS floor,
  MOD(10, 3) AS mod,
  ROUND(3.14) AS round
FROM [jsonFile](./examples/data/products.tmp).data.products
```

## 5. Joining Data Sources

Use `JOIN` to join data sources in your query. You can join multiple data sources in a single query. When you are joining
data sources, you must specify alias `AS` and `ON` condition. Multiple using of `ON` statement rewrites last condition.

```sql
FROM file_reference
[
    {[INNER] JOIN | {LEFT|RIGHT|FULL} [OUTER] JOIN}
    file_reference
    AS alias_reference
    ON where_condition
]
```

- _**file_reference**_: is a [FileQuery](#2-file-query).

| Join type | Description      |
|-----------|------------------|
| `INNER`   | Inner join       |
| `LEFT`    | Left outer join  |
| `RIGHT`   | Right outer join |
| `FULL`    | Full outer join  |

**Example:**

```sql
SELECT
    id,
    name,
    o.id AS orderId,
    o.total_price AS totalPrice
FROM [json](./examples/data/users.json).data.users
LEFT JOIN
    [xml](./examples/data/orders.xml).orders.order AS o
        ON id = user_id
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
                
comparison_operator: = | >= | > | <= | < | <> | != | !== | == | LIKE | NOT LIKE | IS | IS NOT | IN | NOT IN

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
FROM [json](./examples/data/users.json).data.users
WHERE
    id = 1
    AND name = 'John Doe'
    OR name = 'Jane Doe'
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

**Example:**

```sql
SELECT
    brand.code AS brandCode,
    GROUP_CONCAT(id, "/") AS products,
    SUM(price) AS totalPrice,
    COUNT(productId) AS productCount,
    AVG(price) AS avgPrice,
    MIN(price) AS minPrice,
    MAX(price) AS maxPrice
FROM [jsonFile](./examples/data/products.tmp).data.products
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
FROM [jsonFile](./examples/data/products.tmp).data.products
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
FROM [jsonFile](./examples/data/products.tmp).data.products
LIMIT 10
OFFSET 5
```

## Next steps
- [Opening Files](opening-files.md)
- [Fluent API](fluent-api.md)
- File Query Language
- [Fetching Data](fetching-data.md)
- [Query Life Cycle](query-life-cycle.md)
- [Query Inspection and Benchmarking](query-inspection-and-benchmarking.md)

or go back to [README.md](../README.md).
