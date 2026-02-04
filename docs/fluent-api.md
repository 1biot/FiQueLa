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

Using `DISTINCT` will remove duplicate values from the selected fields.

```php
$query->select('id', 'name')->distinct();
```

`selectAll()` can be combined with additional fields, matching MySQL behavior.

```php
$query->selectAll()
    ->select('totalPrice');
```

Using `EXCLUDE` will remove the selected fields from the query results. It's useful when you're applying functions
in the SELECT clause and don't want those fields included in the output. Dot notation is supported for nested fields.

```php
$query->select('id', 'name')
    ->round('totalPrice', 2)->as('finalPrice')
    ->exclude('totalPrice');

// results
// [
//     'id' => 1,
//     'name' => 'John Doe',
//     'finalPrice' => 100.23 // for example, the original value of totalPrice was 100.234567, but it was excluded.
// ]
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

| Function       | Description                                                    |
|----------------|----------------------------------------------------------------|
| `concat`       | Concatenate values with no separator.                          |
| `concatWS`     | Concatenate values with separator.                             |
| `lower`        | Convert string to lower case.                                  |
| `upper`        | Convert string to upper case.                                  |
| `reverse`      | Reverse string.                                                |
| `explode`      | Split string to array.                                         |
| `implode`      | Join array to string.                                          |
| `fromBase64`   | Decode base64 string.                                          |
| `toBase64`     | Encode string to base64.                                       |
| `randomString` | Generates random string.                                       |
| `matchAgainst` | Create a score by matching against query                       |
| `lpad`         | Left pad string with another string.                           |
| `rpad`         | Right pad string with another string.                          |
| `substring`    | Get substring of string.                                       |
| `locate`       | Find position of substring in string.                          |
| `replace`      | Replace all occurrences of a substring with another substring. |

**Example:**

```php
$query->concat('ArticleNr', 'CatalogNr')->as('CONCAT')
    ->concatWS('/', 'ArticleNr', 'CatalogNr')->as('CONCAT_WS')
    ->lower('name')->as('LOWER')
    ->upper('name')->as('UPPER')
    ->reverse('name')->as('REVERSE')
    ->explode('Partner_Article', '|')->as('EXPLODE')
    ->implode('categories[].id', '|')->as('IMPLODE')
    ->fromBase64('base64String')->as('BASE64_ENCODE')
    ->toBase64('string')->as('BASE64_DECODE')
    ->randomString(16)->as('RANDOM_STRING')
    ->matchAgainst(['name'], 'search query')->as('MATCH_AGAINST')
    ->lpad('name', 10, '0')->as('LPAD')
    ->rpad('name', 10, '0')->as('RPAD')
    ->substring('name', 0, 5)->as('SUBSTRING')
    ->locate('05', 'name')->as('LOCATE');
    ->replace('SQL Tutorial', 'SQL', 'HTML')->as('REPLACE');
```

### Utils functions

| Function           | Description                                                             |
|--------------------|-------------------------------------------------------------------------|
| `arrayCombine`     | Combine two array with keys and array with values into a single array   |
| `arrayFiler`       | Filter array from empty values                                          |
| `arrayMerge`       | Merge two arrays into a single array                                    |
| `colSplit`         | Split array field into columns with optional format and key field       |
| `cast`             | Cast value to the requested type                                        |
| `coalesce`         | Coalesce values (first non-null value)                                  |
| `coalesceNotEmpty` | Coalesce values when not empty (first non-empty value)                  |
| `formatDate`       | Format date field to string                                             |
| `fromUnixTime`     | Convert unix timestamp to date or time                                  |
| `strToDate`        | Parse string to date or time                                            |
| `dateDiff`         | Calculate difference between two dates                                  |
| `dateAdd`          | Add interval to date                                                    |
| `dateSub`          | Subtract interval from date                                             |
| `year`             | Get year from date                                                      |
| `month`            | Get month from date                                                     |
| `day`              | Get day of month from date                                              |
| `now`              | Get current date and time                                               |
| `currentTimestamp` | Get current unix timestamp                                              |
| `currentDate`      | Get current date                                                        |
| `currentTime`      | Get current time                                                        |
| `length`           | Get length of value. Recognizes arrays as count, null as 0 and strings  |
| `randomBytes`      | Generates cryptographically secure random bytes.                        |
| `if`               | If condition, if true return first value, else second value             |
| `ifNull`           | If value is null return second value, else first value                  |
| `isNull`           | Check if value is null                                                  |
| `case`             | Case statement for multiple conditions                                  |

**Example:**

```php
$query->arrayCombine('fieldWithArrayKeys', 'fieldWithArrayValues')->as('ARRAY_COMBINE')
    ->arrayFilter('fieldWithArray1')->as('ARRAY_FILTER')
    ->arrayMerge('fieldWithArray1', 'fieldWithArray2')->as('ARRAY_MERGE')
    ->colSplit('items', 'item_%index', 'id')
    ->case()
        ->whenCase('stock > 100', 'more than 100')
        ->whenCase('stock > 50', 'more than 50')
        ->whenCase('stock > 10', 'more than 10')
        ->whenCase('stock > 0', 'last stock items')
        ->elseCase('out of stock')
    ->endCase()->as('CASE_WHEN')
    ->coalesce('whatever', 'ArticleNr')->as('COALESCE')
    ->coalesceNotEmpty('whatever', 'ArticleNr')->as('COALESCE_NE')
    ->cast('price', \FQL\Enum\Type::FLOAT)->as('CAST')
    ->strToDate('dateString', '%Y-%m-%d')->as('STR_TO_DATE')
    ->formatDate('dateField', 'Y-m-d')->as('DATE_FORMAT')
    ->fromUnixTime('dateField', 'Y-m-d')->as('FROM_UNIXTIME')
    ->length('fieldWithArrayKeys')->as('keysCount')
    ->length('Hello world')->as('stringLength')
    ->randomBytes(16)->as('RANDOM_BYTES')
    ->if('`some field` IN (1, 2, 3)', 'true', 'false')->as('IF')
    ->ifNull('field', 'yes')->as('IFNULL')
    ->isNull('`whatever field`')->as('ISNULL')
    ->dateDiff('dateField1', 'dateField2')->as('DATE_DIFF')
    ->dateAdd('dateField', '+1 day')->as('DATE_ADD')
    ->dateSub('dateField', '+1 day')->as('DATE_SUB')
    ->year('dateField')->as('YEAR')
    ->month('dateField')->as('MONTH')
    ->day('dateField')->as('DAY')
    ->now()->as('NOW')
    ->currentTimestamp()->as('CURRENT_TIMESTAMP')
    ->currentDate()->as('CURDATE')
    ->currentTime()->as('CURTIME');
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

### Custom functions

You can create your own custom functions by extending the `\FQL\Functions\Core\SingleFieldFunction`
or `\FQL\Functions\Core\MultiFieldFunction` or `\FQL\Functions\Core\NoFieldFunction` classes.

**Example:**

```php
use FQL\Functions\Core\SingleFieldFunction;

class CustomFunction extends SingleFieldFunction
{
    public function __invoke(array $item, array $resultItem): mixed
    {
        $fieldValue = (string) $this->getFieldValue($this->field, $item, $resultItem);
        return $fieldValue . '_custom';
    }
    
    public function __toString(): string
    {
        return sprintf('myCustomFunction(%s)', $this->field);
    }
}

$query->custom(new CustomFunction('name'))->as('CUSTOM');
```


## 3. Joining Data Sources

Use `JOIN` to join data sources in your query. You can join multiple data sources in a single query.

### Join types

| Join type | Description      |
|-----------|------------------|
| `INNER`   | Inner join       |
| `LEFT`    | Left outer join  |
| `RIGHT`   | Right outer join |
| `FULL`    | Full outer join  |

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

| Enum constant           | Operator      | Description                                                                                                 |
|-------------------------|---------------|-------------------------------------------------------------------------------------------------------------|
| `EQUAL`                 | `=`           |                                                                                                             |
| `EQUAL_STRICT`          | `==`          |                                                                                                             |
| `NOT_EQUAL`             | `!=`          |                                                                                                             |
| `NOT_EQUAL_STRICT`      | `!==`         |                                                                                                             |
| `GREATER_THAN`          | `>`           |                                                                                                             |
| `GREATER_THAN_OR_EQUAL` | `>=`          |                                                                                                             |
| `LESS_THAN`             | `<`           |                                                                                                             |
| `LESS_THAN_OR_EQUAL`    | `<=`          |                                                                                                             |
| `IN`                    | `IN`          | Compare if field is in array list.                                                                          |
| `NOT_IN`                | `NOT IN`      | Same as `IN` operator but with opposite result.                                                             |
| `LIKE`                  | `LIKE`        | Fully compatible `LIKE` in MYSQL databases. Supports `_` and `%`.                                           |
| `NOT_LIKE`              | `NOT LIKE`    | Same as `LIKE` operator but with opposite result.                                                           |
| `IS`                    | `IS`          | Supported types: `BOOLEAN`, `TRUE`, `FALSE`, `NUMBER`, `INT`, `DOUBLE`, `STRING`, `NULL`, `ARRAY`, `OBJECT` |
| `NOT_IS`                | `IS NOT`      | Same as `IS` operator but with opposite result.                                                             |
| `BETWEEN`               | `BETWEEN`     | Compare if field is between two values.                                                                     |
| `NOT_BETWEEN`           | `NOT BETWEEN` | Same as `BETWEEN` operator but with opposite result.                                                        |

**Example:**

```php
use FQL\Enum\Operator;

$query->where('price', Operator::GREATER_THAN, 100)
    ->and('description', Operator::LIKE, '%very usefully')
    ->or('price', Operator::BETWEEN, [300, 500]);
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
`count()`, `sum()`, `min()`, `max()` and `groupConcat()` accept a `bool $distinct` parameter.

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

```php
$query->count('category.id', true)->as('COUNT_DISTINCT')
    ->sum('price', true)->as('SUM_DISTINCT')
    ->min('price', true)->as('MIN_DISTINCT')
    ->max('price', true)->as('MAX_DISTINCT')
    ->groupConcat('name', ',', true)->as('GROUP_CONCAT_DISTINCT');
```

## 6. Sorting

Use the `orderBy()` method to sort the data in your query results. You can use the `limit()` and `offset()` methods to filter the data.

### Sorting by

| Sorting by | Description  |
|------------|--------------|
| `ASC`      | Ascending    |
| `DESC`     | Descending   |

**Example:**

```php
use FQL\Enum\Sort;

$query->randomString(16)->as('RANDOM_STRING')
    ->orderBy('price', Sort::ASC)
    ->orderBy('name', Sort::DESC);
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
- [FiQueLa CLI](fiquela-cli.md)
- [Query Inspection and Benchmarking](query-inspection-and-benchmarking.md)

or go back to [README.md](../README.md).
