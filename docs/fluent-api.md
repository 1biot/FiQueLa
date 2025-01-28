# Fluent API

Fluent API is a way to build queries in a more readable and maintainable way. It is a chain of methods that can be called

**table of contents**:

* _1_ - [Select and Alias Fields](#va-joining-data-sources)
* _2_ - [Functions](#va-joining-data-sources)
* _3_ - [Joining Data Sources](#va-joining-data-sources)
* _4_ - [Conditions](#va-joining-data-sources)
* _5_ - [Grouping and Aggregations](#vb-aggregations-and-functions)
* _6_ - [Sorting and Filtering](#vc-sorting-and-filtering)
* _7_ - [Pagination and Limits](#vd-pagination-and-limits)

## 1. Select and Alias Fields

Use this method to customize the fields included in your query results. It supports dot notation for selecting nested fields. If you call the `select()`
 method multiple times, the fields will be merged into a single selection.

**Examples:**

```php
$query->select('id, name, address.city, address.state');
```

is the same as:

```php
$query->select('id', 'name', 'address.city', 'address.state');
```

and same as:

```php
$query->select('id', 'name')
    ->select('address.city', 'address.state');
```

You can combine the `select()` method with the `as()` method to alias fields in the query results.
There is the limitation that you can only alias tha last selected field.

```php
$query->select('id')->as('clientId');
```

**Limitation:**

```php
$query->select('id', 'name')->as('o');
```

Creates an alias only for the last selected field, which is `name` like `id, name AS o`

## 2. Functions

Use this method to apply functions to the fields in your query results. You can use the `as()` method to alias the
result of the function.

### String functions

| Function       | Description                           |
|----------------|---------------------------------------|
| `concat`       | Concatenate values with no separator. |
| `concatWS`     | Concatenate values with separator.    |
| `length`       | Get length of string.                 |
| `lower`        | Convert string to lower case.         |
| `upper`        | Convert string to upper case.         |
| `reverse`      | Reverse string.                       |
| `explode`      | Split string to array.                |
| `implode`      | Join array to string.                 |
| `fromBase64`   | Decode base64 string.                 |
| `toBase64`     | Encode string to base64.              |
| `randomString` | Generates random string.              |

**Example:**

```php
$query->concat('ArticleNr', 'CatalogNr')->as('CONCAT')
    ->concatWS('/', 'ArticleNr', 'CatalogNr')->as('CONCAT_WS')
    ->length('name')->as('LENGTH')
    ->lower('name')->as('LOWER')
    ->upper('name')->as('UPPER')
    ->reverse('name')->as('REVERSE')
    ->explode('Partner_Article', '|')->as('EXPLODE')
    ->implode('categories[]->id', '|')->as('IMPLODE')
    ->fromBase64('base64String')->as('BASE64_ENCODE')
    ->toBase64('string')->as('BASE64_DECODE')
    ->randomString(16)->as('RANDOM_STRING');
```

### Utils functions

| Function           | Description                                            |
|--------------------|--------------------------------------------------------|
| `coalesce`         | Coalesce values (first non-null value)                 |
| `coalesceNotEmpty` | Coalesce values when not empty (first non-empty value) |
| `randomBytes`      | Generates cryptographically secure random bytes.       |

**Example:**

```php
$query->coalesce('whatever', 'ArticleNr')->as('COALESCE')
    ->coalesceNotEmpty('whatever', 'ArticleNr')->as('COALESCE_NE')
    ->randomBytes(16)->as('RANDOM_BYTES');
```

### Hashing functions

| Function | Description                 |
|----------|-----------------------------|
| `md5`    | MD5 algorithm for hashing   |
| `sha1`   | SHA1 algorithm for hashing  |

**Example:**

```php
$query->md5('id')->as('MD5')
    ->sha1('name')->as('SHA1');
```

### Math functions

| Function | Description                 |
|----------|-----------------------------|
| `ceil`   | Round number up             |
| `floor`  | Round number down           |
| `modulo` | Modulo operation            |
| `round`  | Round number mathematically |

**Example:**

```php
$query->ceil('price')->as('CEIL')
    ->floor('price')->as('FLOOR')
    ->modulo('price', 2)->as('MOD')
    ->round('price', 1)->as('ROUND');
```

## 3. Joining Data Sources

Use `JOIN` to join data sources in your query. You can join multiple data sources in a single query.

### Join types

| Join type | Description |
|-----------|-------------|
| `INNER`   | Inner join  |
| `LEFT`    | Left join   |
| `RIGHT`   | ❌          |
| `FULL`    | ❌          |

**Example:**

```php
use FQL\Enum\Operator;
use FQL\Query;

$innerData = Query\Provider::fromFileQuery('(file.xml).SHOP.SHOPITEM');
$leftData = Query\Provider::fromFileQuery('[json](file.tmp).data.customers');

$query = Query\Provider::fromFile('./path/to/file.csv')
    ->innerJoin($innerData, 'p')
        ->on('rightId', Operator::EQUAL, 'leftId')
    ->leftJoin($leftData, 'c')
        ->on('rightId', Operator::EQUAL, 'leftId');
```

## 4. Conditions

Use the `where()` method to filter the data in your query results before any aggregation. You can use the `and()` and `or()` methods to combine
multiple conditions. Conditions support dot notation for nested fields and brings the support for grouping conditions with
`whereGroup()`, `andGroup`, `orGroup` and `endGroup()`.

### Logical operators

| Operator | Description  |
|----------|--------------|
| `AND`    | Logical AND  |
| `OR`     | Logical OR   |
| `XOR`    | Logical XOR  |

**Example:**

```php
$query->where(...)
    ->and(...)
    ->or(...)
    ->xor(...);
```

### Operators

Operators are enums that represent the comparison operators used in the conditions. To use operators, you must import the `Operator` enum.

```php
use FQL\Enum\Operator;
```

| Operator                | Description |
|-------------------------|-------------|
| `EQUAL`                 | `=`         |
| `EQUAL_STRICT`          | `==`        |
| `NOT_EQUAL`             | `!=`        |
| `NOT_EQUAL_STRICT`      | `!==`       |
| `GREATER_THAN`          | `>`         |
| `GREATER_THAN_OR_EQUAL` | `>=`        |
| `LESS_THAN`             | `<`         |
| `LESS_THAN_OR_EQUAL`    | `<=`        |
| `IN`                    | `IN`        |
| `NOT_IN`                | `NOT IN`    |
| `LIKE`                  | `LIKE`      |
| `NOT_LIKE`              | `NOT LIKE`  |
| `IS`                    | `IS`        |
| `NOT_IS`                | `IS NOT`    |

**Example:**

```php
use FQL\Enum\Operator;

$query->where('price', Operator::GREATER_THAN, 100)
    ->and('description', Operator::LIKE, '%very usefully')
    ->or('price', Operator::LESS_THAN_OR_EQUAL, 300);
```

### Grouping conditions

You can group conditions using the `whereGroup()`, `andGroup()`, `orGroup()` and `endGroup()` methods. This is useful
when you want to use more complex conditions.

**Example:**

```php
$query->where('price', Operator::GREATER_THAN, 100)
    ->andGroup()
        ->where('description', Operator::LIKE, '%very usefully')
        ->or('price', Operator::LESS_THAN_OR_EQUAL, 300)
        ->orGroup()
            ->where('price', Operator::EQUAL, 200)
            ->and('description', Operator::LIKE, '%very usefully')
        ->endGroup()
    ->endGroup()
    ->orGroup()
        ->where('price', Operator::LESS_THAN_OR_EQUAL, 300);
    ->endGroup();
```

This produces the following conditions:

```sql
price > 100
AND (
    description LIKE '%very usefully'
    OR price <= 300
    OR (
        price = 200
        AND description LIKE '%very usefully'
    )
)
```

## 5. Grouping and Aggregations

Use the `groupBy()` method to group the data in your query results. You can use the `having()` method to filter the grouped data.
Also, you can use these aggregations functions `count()`, `sum()`, `avg()`, `min()`, `max()` and `groupConcat()` methods to aggregate the data.

`groupBy()` is last method that using dot notation for nested fields.

**Example:**

```php
$query->groupBy('category.id');
```

### Aggregations

| Function      | Description                 |
|---------------|-----------------------------|
| `count`       | Count rows                  |
| `sum`         | Sum values                  |
| `avg`         | Average values              |
| `min`         | Minimum value               |
| `max`         | Maximum value               |
| `groupConcat` | Concatenate values          |

**Example:**

```php
$query->count('category.id')->as('COUNT')
    ->sum('price')->as('SUM')
    ->avg('price')->as('AVG')
    ->min('price')->as('MIN')
    ->max('price')->as('MAX')
    ->groupConcat('name')->as('GROUP_CONCAT')
    ->groupBy('category.id')
    ->having('COUNT', Operator::GREATER_THAN, 10)
    ->or('SUM', Operator::GREATER_THAN, 1000);
```

## 6. Sorting

Use the `orderBy()` method to sort the data in your query results. You can use the `limit()` and `offset()` methods to filter the data.

### Sorting by

| Sorting by | Description  |
|------------|--------------|
| `ASC`      | Ascending    |
| `DESC`     | Descending   |
| `SHUFFLE`  | Shuffling    |
| `NATSORT`  | Natural sort |

**Example:**

```php
use FQL\Enum\Sort;

$query->randomString(16)->as('RANDOM_STRING')
    ->orderBy('price', Sort::ASC)
    ->orderBy('name', Sort::DESC)
    ->orderBy('RANDOM_STRING')->shuffle()
    ->orderBy('id')->natural();
```

## 7. Pagination

Use the `limit()` and `offset()` methods to paginate the data in your query results.

**Example:**

```php
$query->offset(40)
    ->limit(20);
```

or you can use the `page()` method to paginate the data in your query results.

```php
$query->page(2, perPage: 20);
```

## Next steps

- [Opening Files](opening-files.md)
- Fluent API
- [File Query Language](file-query-language.md)
- [Fetching Data](fetching-data.md)
- [Query Life Cycle](query-life-cycle.md)
- [Query Inspection and Benchmarking](query-inspection-and-benchmarking.md)

or go back to [README.md](../README.md).
