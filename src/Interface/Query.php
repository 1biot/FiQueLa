<?php

namespace FQL\Interface;

use FQL\Enum\Fulltext;
use FQL\Enum\LogicalOperator;
use FQL\Enum\Operator;
use FQL\Enum\Sort;
use FQL\Enum\Type;
use FQL\Exception;
use FQL\Query\FileQuery;
use FQL\Results;
use FQL\Results\ResultsProvider;

/**
 * @phpstan-type InArrayList string[]|int[]|float[]|array<int|string>
 * @phpstan-type ConditionValue int|float|string|InArrayList|Type
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
interface Query extends \Stringable
{
    public const SELECT_ALL = '*';
    public const FROM_ALL = self::SELECT_ALL;

    public const SELECT = 'SELECT';
    public const DISTINCT = 'DISTINCT';
    public const CASE = 'CASE';
    public const WHEN = 'WHEN';
    public const THEN = 'THEN';
    public const ELSE = 'ELSE';
    public const END = 'END';
    public const EXCLUDE = 'EXCLUDE';
    public const AS = 'AS';
    public const ON = 'ON';
    public const BY = 'BY';
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
     * @param string ...$fields The fields to include in the selection.
     * @throws Exception\SelectException
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
    public function select(string ...$fields): Query;

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

    public function distinct(bool $distinct = true): Query;

    public function exclude(string ...$fields): Query;

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

    public function substring(string $field, int $start, ?int $length = null): Query;
    public function locate(string $substring, string $field, ?int $position = null): Query;

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
     * Generate a random string in the query.
     *
     * This method allows you to generate a random string in the query results.
     * You can specify the length of the random string to generate. If you call the `randomString()`
     * method multiple times, the random strings will be generated into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->randomString(16);
     * // Result: SELECT RANDOM_STRING(16)
     * ```
     *
     * Use this method to generate random strings in your query results.
     */
    public function randomString(int $length = 10): Query;

    /**
     * Apply the FROM_BASE64 function to a field in the query.
     *
     * This method allows you to apply the FROM_BASE64 function to a field in the query results.
     * If you call the `toBase64()` method multiple times, the fields will be encoded into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->toBase64('field');
     * // Result: SELECT FROM_BASE64(field)
     * ```
     *
     * Use this method to encode a field in your query results.
     */
    public function fromBase64(string $field): Query;

    /**
     * Apply the TO_BASE64 function to a field in the query.
     *
     * This method allows you to apply the TO_BASE64 function to a field in the query results.
     * If you call the `toBase64()` method multiple times, the fields will be encoded into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->toBase64('field');
     * // Result: SELECT TO_BASE64(field)
     * ```
     *
     * Use this method to encode a field in your query results.
     */
    public function toBase64(string $field): Query;

    /**
     * Generate random bytes in the query.
     *
     * This method allows you to generate random bytes in the query results.
     * You can specify the length of the random bytes to generate. If you call the `randomBytes()`
     * method multiple times, the random bytes will be generated into a single result.
     *
     * Example usage:
     *
     * ```
     * $query->randomBytes(16);
     * // Result: SELECT RANDOM_BYTES(16)
     * ```
     *
     * Use this method to generate random bytes in your query results.
     */
    public function randomBytes(int $length = 10): Query;

    /** @param string[] $fields */
    public function fulltext(array $fields, string $searchQuery): Query;
    /** @param string[] $fields */
    public function matchAgainst(array $fields, string $searchQuery, ?Fulltext $mode = null): Query;

    public function leftPad(string $field, int $length, string $padString = ' '): Query;
    public function rightPad(string $field, int $length, string $padString = ' '): Query;

    public function replace(string $field, string $search, string $replace): Query;

    public function arrayCombine(string $keysArrayField, string $valueArrayField): Query;
    public function arrayMerge(string $keysArrayField, string $valueArrayField): Query;
    public function arrayFilter(string $field): Query;
    public function formatDate(string $dateField, string $format = 'c'): Query;
    public function currentDate(bool $numeric = false): Query;
    public function currentTime(bool $numeric = false): Query;
    public function currentTimestamp(): Query;
    public function now(bool $numeric = false): Query;
    public function dateDiff(string $date, string $secondDate): Query;

    public function if(string $conditionString, string $trueStatement, string $falseStatement): Query;
    public function ifNull(string $field, string $trueStatement): Query;
    public function isNull(string $field): Query;
    public function case(): Query;
    public function whenCase(string $conditionString, string $thenStatement): Query;
    public function elseCase(string $defaultCaseStatement): Query;
    public function endCase(): Query;

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
    public function rightJoin(Query $query, string $alias): Query;
    public function fullJoin(Query $query, string $alias): Query;
    public function on(string $leftKey, Operator $operator, string $rightKey): Query;

    /**
     * Add a conditional clause to the query.
     * @param ConditionValue $value The value to compare against.
     */
    public function where(string $field, Operator $operator, array|float|int|string|Type $value): Query;

    /** @param ConditionValue $value The value to compare against. */
    public function having(string $field, Operator $operator, array|float|int|string|Type $value): Query;

    /** @param ConditionValue $value */
    public function and(string $field, Operator $operator, int|float|string|array|Type $value): Query;

    /** @param ConditionValue $value */
    public function or(string $field, Operator $operator, int|float|string|array|Type $value): Query;

    /** @param ConditionValue $value */
    public function xor(string $field, Operator $operator, int|float|string|array|Type $value): Query;

    /** Add a conditional group clause in WHERE context to the query */
    public function whereGroup(): Query;

    /** Add a conditional group clause in HAVING context to the query.*/
    public function havingGroup(): Query;

    public function andGroup(): Query;
    public function orGroup(): Query;
    public function endGroup(): Query;

    /**
     * Specify the fields to group by in the query.
     * @param string ...$fields The fields to group by.
     */
    public function groupBy(string ...$fields): Query;
    public function count(?string $field = null): Query;
    public function sum(string $field): Query;
    public function avg(string $field): Query;
    public function min(string $field): Query;
    public function max(string $field): Query;
    public function groupConcat(string $field, string $separator = ','): Query;

    public function orderBy(string $field, Sort $direction = Sort::ASC): Query;
    public function sortBy(string $field, Sort $direction = Sort::ASC): Query;
    public function asc(): Query;
    public function desc(): Query;

    public function offset(int $offset): Query;
    public function limit(int $limit, ?int $offset = null): Query;
    public function page(int $page, int $perPage = self::PER_PAGE_DEFAULT): Query;

    /**
     * @template T of ResultsProvider
     * @param class-string<T>|null $resultClass
     * @return ResultsProvider
     */
    public function execute(?string $resultClass = null): Results\ResultsProvider;

    /**
     * @throws Exception\InvalidFormatException
     */
    public function provideFileQuery(): FileQuery;
}
