<?php

namespace JQL;

use JQL\Enum\LogicalOperator;
use JQL\Enum\Operator;
use JQL\Enum\Sort;

/**
 * @phpstan-type InArrayList string[]|int[]|float[]|array<int|string>
 * @phpstan-type ConditionValue int|float|string|InArrayList
 * @phpstan-type BaseCondition array{
 *     key: string,
 *     operator: Operator,
 *     value: ConditionValue
 * }
 * @phpstan-type Condition array{
 *     type: LogicalOperator,
 *     key: string,
 *     operator: Operator,
 *     value: ConditionValue
 * }
 * @phpstan-type ConditionGroup array{
 *     type: LogicalOperator,
 *     group: BaseCondition[]
 * }
 */
interface Query
{
    /**
     * Enable or disable grouping of conditions in a query.
     *
     * By default, grouping is enabled. When grouping is enabled, you can create
     * groups of conditions enclosed in parentheses. If grouping is disabled,
     * all conditions will be joined without parentheses.
     *
     * Example with grouping (default):
     * ```
     * $query->where($cond1)->and($cond2)->or($cond3)->or($cond4);
     * // Result: (cond1 AND cond2) OR (cond3 OR cond4)
     * ```
     *
     * Example without grouping:
     * ```
     * $query->disableGrouping()
     *       ->where($cond1)->and($cond2)->or($cond3)->or($cond4);
     * // Result: cond1 AND cond2 OR cond3 OR cond4
     * ```
     *
     * Use this method to toggle grouping behavior as needed.
     */
    public function setGrouping(bool $grouping): Query;

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

    public function where(string $key, Operator $operator, array|float|int|string $value): Query;

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

    public function and(string $key, Operator $operator, int|float|string|array $value): Query;

    /**
     * @param string[]|int[]|float[] $values
     */
    public function in(string $key, array $values): Query;

    /**
     * @param string[]|int[]|float[] $values
     */
    public function orIn(string $key, array $values): Query;

    /**
     * @param string[]|int[]|float[] $values
     */
    public function notIn(string $key, array $values): Query;

    /**
     * @param string[]|int[]|float[] $values
     */
    public function orNotIn(string $key, array $values): Query;

    /**
     * @param ConditionValue $value
     */
    public function or(string $key, Operator $operator, int|float|string|array $value): Query;

    public function is(string $key, null|int|float|string $value): Query;

    public function orIs(string $key, null|int|float|string $value): Query;

    public function isNull(string $key): Query;

    public function isNotNull(string $key): Query;

    public function orIsNull(string $key): Query;

    public function orIsNotNull(string $key): Query;

    public function limit(int $limit, ?int $offset = null): Query;

    public function offset(int $offset): Query;

    public function orderBy(string $key, Sort $direction = Sort::ASC): Query;

    public function fetchAll(?string $dto = null): \Generator;

    public function fetchNth(int|string $n, ?string $dto = null): \Generator;

    public function fetch(?string $dto = null): mixed;

    public function fetchSingle(string $key): mixed;

    public function count(): int;

    public function sum(string $key): float;

    public function avg(string $key, int $decimalPlaces = 2): float;

    public function test(): string;
}
