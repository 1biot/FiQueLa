<?php

namespace UQL\Query;

use UQL\Enum\LogicalOperator;
use UQL\Enum\Operator;
use UQL\Enum\Sort;

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
interface Query extends \Countable
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
     * $query->select('user.id')->as('userId');
     * // Result: SELECT user.id AS userId
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

    public function offset(int $offset): Query;
    public function limit(int $limit, ?int $offset = null): Query;
    public function page(int $page, int $perPage = self::PER_PAGE_DEFAULT): Query;

    public function orderBy(string $key, Sort $direction = Sort::ASC): Query;
    public function sortBy(string $key, Sort $direction = Sort::ASC): Query;
    public function asc(): Query;
    public function desc(): Query;
    public function natural(): Query;
    public function shuffle(): Query;

    public function fetchAll(?string $dto = null): \Generator;
    public function fetchNth(int|string $n, ?string $dto = null): \Generator;
    public function fetch(?string $dto = null): mixed;
    public function fetchSingle(string $key): mixed;
    public function count(): int;
    public function sum(string $key): float;
    public function avg(string $key, int $decimalPlaces = 2): float;

    public function test(): string;
}
