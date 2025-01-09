<?php

namespace UQL\Query;

use UQL\Enum\LogicalOperator;
use UQL\Enum\Operator;
use UQL\Enum\Sort;
use UQL\Results\ResultsProvider;

/**
 * @phpstan-type InArrayList string[]|int[]|float[]|array<int|string>
 * @phpstan-type ConditionValue null|int|float|string|InArrayList
 * @phpstan-type Condition array{
 *     type: LogicalOperator,
 *     key: string,
 *     operator: Operator,
 *     value: ConditionValue
 * }
 * @phpstan-type ConditionGroup array{
 *     type: LogicalOperator,
 *     group: Condition[]
 * }
 */
interface Query
{
    public const SELECT_ALL = '*';
    public const FROM_ALL = self::SELECT_ALL;

    public const SELECT = 'SELECT';
    public const AS = 'AS';
    public const FROM = 'FROM';
    public const WHERE = 'WHERE';
    public const HAVING = 'HAVING';
    public const OFFSET = 'OFFSET';
    public const LIMIT = 'LIMIT';
    public const PER_PAGE_DEFAULT = 10;

    /**
     * Specify fields to select in a query.
     *
     * This method allows you to define the fields you want to include in the selection.
     * It supports dot notation for selecting nested fields. If you call the `select()`
     * method multiple times, the fields will be merged into a single selection.
     *
     * Example with simple fields:
     *
     * ```
     * $query->select('id, name');
     * // Result: SELECT id, name
     * ```
     *
     * Example with nested fields:
     *
     * ```
     * $query->select('user.id, user.profile.email');
     * // Result: SELECT user.id, user.profile.email
     * ```
     *
     * Example with multiple calls to `select()`:
     *
     * ```
     * $query->select('id')->select('name, email');
     * // Result: SELECT id, name, email
     * ```
     *
     * Use this method to customize the fields included in your query results.
     */
    public function select(string $fields): Query;

    /**
     * Select all fields in the query.
     *
     * This method allows you to select all fields in the query, including nested fields.
     * It is equivalent to using the `SELECT *` SQL statement.
     *
     * Example usage:
     *
     * ```
     * $query->selectAll();
     * // Result: SELECT *
     * ```
     *
     * Use this method to select all fields in the query results.
     */
    public function selectAll(): Query;

    /**
     * Alias the last selected field.
     *
     * This method allows you to define an alias for the last field selected in the query.
     * It supports dot notation for selecting nested fields. The alias will be used
     * as the key in the result array.
     *
     * Example with simple fields:
     *
     * ```
     * $query->select('user_id')->as('userId');
     * // Result: SELECT user_id AS userId
     * ```
     *
     * Example with nested fields:
     *
     * ```
     * $query->select('user.profile.email')->as('email');
     * // Result: SELECT user.profile.email AS email
     * ```
     *
     * Use this method to customize the field names in your query results.
     */
    public function as(string $alias): Query;

    /**
     * Concatenate fields in the query.
     *
     * This method allows you to concatenate multiple fields in the query results.
     * You can specify a separator to use between the fields. If you call the `concat()`
     * method multiple times, the fields will be concatenated into a single result.
     *
     * Example with simple fields:
     *
     * ```
     * $query->concatWithSeparator(', ', 'first_name', 'last_name');
     * // Result: SELECT CONCAT_WS(", ", first_name, last_name)
     * ```
     *
     * Use this method to combine fields in your query results.
     */
    public function concatWithSeparator(string $separator, string ...$fields): Query;

    /**
     * Concatenate fields in the query.
     *
     * This method allows you to concatenate multiple fields in the query results.
     * If you call the `concat()` method multiple times, the fields will be concatenated
     * into a single result.
     *
     * Example with simple fields:
     *
     * ```
     * $query->concat('first_name', 'last_name');
     * // Result: SELECT CONCAT(first_name, last_name)
     * ```
     *
     * Use this method to combine fields in your query results.
     */
    public function concat(string ...$fields): Query;

    /**
     * Coalesce fields in the query.
     *
     * This method allows you to select the first non-null value from a list of fields.
     * If you call the `coalesce()` method multiple times, the fields will be coalesced
     * into a single result.
     *
     * Example with simple fields:
     *
     * ```
     * $query->coalesce('first_name', 'last_name');
     * // Result: SELECT COALESCE(first_name, last_name)
     * ```
     *
     * Use this method to select the first non-null value from a list of fields.
     */
    public function coalesce(string ...$fields): Query;

    /**
     * Coalesce fields in the query.
     *
     * This method allows you to select the first non-empty value from a list of fields.
     *
     * Example with simple fields:
     *
     * ```
     * $query->coalesceNotEmpty('first_name', 'last_name');
     * // Result: SELECT COALESCE_NOT_EMPTY(first_name, last_name)
     * ```
     */
    public function coalesceNotEmpty(string ...$fields): Query;


    /**
     * Explode a field in the query.
     *
     * This method allows you to split a field into an array using a separator.
     * If you call the `explode()` method multiple times, the fields will be exploded
     * into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->explode('tags');
     * // Result: SELECT EXPLODE("tags", ",")
     * ```
     *
     * Use this method to split a field into an array in your query results.
     * @alias split
     */
    public function explode(string $field, string $separator = ','): Query;
    public function split(string $field, string $separator = ','): Query;

    /**
     * Implode a field in the query.
     *
     * This method allows you to join an array of values into a single string using a separator.
     * If you call the `implode()` method multiple times, the fields will be imploded
     * into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->implode('tags');
     * // or
     * $query->glue('tags', '|');
     * // Result: SELECT IMPLODE("tags", ",")
     * ```
     *
     * Use this method to join an array of values into a single string in your query results.
     * @alias glue
     */
    public function implode(string $field, string $separator = ','): Query;
    public function glue(string $field, string $separator = ','): Query;

    /**
     * Apply the SHA1 function to a field in the query.
     *
     * This method allows you to apply the SHA1 function to a field in the query results.
     * If you call the `sha1()` method multiple times, the fields will be hashed into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->sha1('password');
     * // Result: SELECT SHA1(password)
     * ```
     *
     * Use this method to hash a field in your query results.
     */
    public function sha1(string $field): Query;

    /**
     * Apply the MD5 function to a field in the query.
     *
     * This method allows you to apply the MD5 function to a field in the query results.
     * If you call the `md5()` method multiple times, the fields will be hashed into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->md5('password');
     * // Result: SELECT MD5(password)
     * ```
     *
     * Use this method to hash a field in your query results.
     */
    public function md5(string $field): Query;

    public function lower(string $field): Query;
    public function upper(string $field): Query;

    /**
     * Apply the ROUND function to a field in the query.
     *
     * This method allows you to apply the ROUND function to a field in the query results.
     * You can specify the precision to round the field to. If you call the `round()` method
     * multiple times, the fields will be rounded into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->round('price', 2);
     * // Result: SELECT ROUND(price, 2)
     * ```
     *
     * Use this method to round a field in your query results.
     */
    public function round(string $field, int $precision = 0): Query;

    /**
     * Apply the CEIL function to a field in the query.
     *
     * This method allows you to apply the CEIL function to a field in the query results.
     * If you call the `ceil()` method multiple times, the fields will be processed into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->ceil('price');
     * // Result: SELECT CEIL(price)
     * ```
     *
     * Use this method to round a field up to the nearest integer in your query results.
     */
    public function ceil(string $field): Query;

    /**
     * Apply the FLOOR function to a field in the query.
     *
     * This method allows you to apply the FLOOR function to a field in the query results.
     * If you call the `floor()` method multiple times, the fields will be processed into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->floor('price');
     * // Result: SELECT FLOOR(price)
     * ```
     *
     * Use this method to round a field down to the nearest integer in your query results.
     */
    public function floor(string $field): Query;

    /**
     * Apply the MOD function to a field in the query.
     *
     * This method allows you to apply the MOD function to a field in the query results.
     * You can specify the divisor to use for the modulo operation. If you call the `modulo()`
     * method multiple times, the fields will be processed into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->modulo('id', 10);
     * // Result: SELECT MOD(id, 10)
     * ```
     *
     * Use this method to get the remainder of a field divided by a divisor in your query results.
     */
    public function modulo(string $field, int $divisor): Query;

    /**
     * Apply the LENGTH function to a field in the query.
     *
     * This method allows you to apply the LENGTH function to a field in the query results.
     * If you call the `length()` method multiple times, the fields will be processed into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->length('name')->as('length);
     * // Result: SELECT LENGTH(name) AS length
     * ```
     *
     * Use this method to get the length of a field in your query results.
     */
    public function length(string $field): Query;

    /**
     * Reverse a field in the query.
     *
     * This method allows you to reverse a field in the query results.
     * If you call the `reverse()` method multiple times, the fields will be reversed into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->reverse('name');
     * // Result: SELECT REVERSE(name)
     * ```
     *
     * Use this method to reverse a field in your query results.
     */
    public function reverse(string $field): Query;

    /**
     * Specify a specific part of the data to select.
     *
     * This method allows you to target a specific section of the data for selection.
     * It supports dot notation to navigate and select nested fields within a structure.
     *
     * Example with simple fields:
     * ```
     * $query->from('details');
     * // Result: FROM details
     * ```
     *
     * Example with nested fields:
     * ```
     * $query->from('data.products');
     * // Result: FROM data.products
     * ```
     *
     * Use this method to refine your query to a specific part of the data.
     */
    public function from(string $query): Query;

    public function join(Query $query, string $alias): Query;
    public function innerJoin(Query $query, string $alias): Query;
    public function leftJoin(Query $query, string $alias): Query;
    public function on(string $leftKey, Operator $operator, string $rightKey): Query;

    /**
     * Add a conditional clause to the query.
     *
     * This method allows you to specify a condition for filtering data in the query.
     * You can define the field (`$key`), the operator (`$operator`), and the value (`$value`)
     * that the field is compared against.
     *
     * Example usage:
     *  ```
     *  $query->where('age', Operator::GREATER_THAN, 18);
     *  // Result: WHERE age > 18
     *
     *  $query->where('user.status', Operator::EQUAL, 'active');
     *  // Result: WHERE user.status = 'active'
     *
     *  $query->where('id', Operator::IN, [1, 2, 3]);
     *  // Result: WHERE id IN (1, 2, 3)
     *  ```
     *
     * @param string $key The field name to filter by. Supports nested fields using dot notation
     * (e.g., `user.profile.name`).
     * @param Operator $operator The comparison operator (e.g., `Operator::EQUAL`, `Operator::GREATER_THAN`).
     * @param ConditionValue $value The value to compare against.
     * It can be a single value or an array for operators like `IN`.
     * @return Query Returns the current query instance, allowing for method chaining.
     */
    public function where(string $key, Operator $operator, null|array|float|int|string $value): Query;

    /**
     * @param string $key The field name to filter by. Do not support nested fields using dot notation
     * (e.g., `user.profile.name`).
     * @param Operator $operator The comparison operator (e.g., `Operator::EQUAL`, `Operator::GREATER_THAN`).
     * @param ConditionValue $value The value to compare against.
     * It can be a single value or an array for operators like `IN`.
     * @return Query Returns the current query instance, allowing for method chaining.
     */
    public function having(string $key, Operator $operator, null|array|float|int|string $value): Query;

    public function whereGroup(): Query;
    public function andGroup(): Query;
    public function orGroup(): Query;
    public function endGroup(): Query;
    /**
     * Alias for the `where` method.
     *
     * This method serves as a shorthand or alternative name for the `where` method,
     * allowing you to add a conditional clause to the query using a simplified syntax.
     *
     * Example usage:
     *  ```
     *  $query->and('age', Operator::GREATER_THAN, 18);
     *  // Equivalent to: $query->where('age', Operator::GREATER_THAN, 18);
     *  // Result: WHERE age > 18
     *  ```
     *
     * @param ConditionValue $value A predefined condition value, representing the field, operator,
     * and value to filter by.
     * @return Query Returns the current query instance, allowing for method chaining.
     */
    public function and(string $key, Operator $operator, null|int|float|string|array $value): Query;
    /** @param InArrayList $values */
    public function in(string $key, array $values): Query;
    /** @param InArrayList $values */
    public function orIn(string $key, array $values): Query;
    /** @param InArrayList $values */
    public function notIn(string $key, array $values): Query;
    /** @param InArrayList $values */
    public function orNotIn(string $key, array $values): Query;
    /** @param ConditionValue $value */
    public function or(string $key, Operator $operator, null|int|float|string|array $value): Query;
    public function is(string $key, null|int|float|string $value): Query;
    public function orIs(string $key, null|int|float|string $value): Query;
    public function isNull(string $key): Query;
    public function isNotNull(string $key): Query;
    public function orIsNull(string $key): Query;
    public function orIsNotNull(string $key): Query;

    /**
     * Specify the fields to group by in the query.
     *
     * This method allows you to group the results of the query by one or more fields.
     * You can specify multiple fields to group by, and the results will be grouped
     * by the unique combinations of those fields.
     *
     * Example usage:
     *
     * ```
     * $query->groupBy('category');
     * // Result: GROUP BY category
     *
     * $query->groupBy('category', 'brand');
     * // Result: GROUP BY category, brand
     * ```
     *
     * Use this method to group the results of the query by one or more fields.
     */
    public function groupBy(string ...$fields): Query;
    public function count(?string $field = null): Query;
    public function sum(string $key): Query;
    public function avg(string $key): Query;
    public function min(string $key): Query;
    public function max(string $key): Query;
    public function groupConcat(string $field, string $separator = ','): Query;

    public function orderBy(string $key, Sort $direction = Sort::ASC): Query;
    public function sortBy(string $key, Sort $direction = Sort::ASC): Query;
    public function asc(): Query;
    public function desc(): Query;
    public function natural(): Query;
    public function shuffle(): Query;

    public function offset(int $offset): Query;
    public function limit(int $limit, ?int $offset = null): Query;
    public function page(int $page, int $perPage = self::PER_PAGE_DEFAULT): Query;

    public function execute(): ResultsProvider;

    public function test(): string;
}
