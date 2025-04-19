# Changelog

## [2.0.11]
- Do not throw an exception when field in condition does not exist. It was last place where was throwing an exception,
and we need to compare values through `IS NULL` or `IS NOT NULL`.
- Fixed parsing `DATE_FORMAT` second parameter and setting the default value to `c` format (`Y-m-d\TH:i:sP`).

## [2.0.10]
- hotfix, removed dump function
- Use this version instead of v2.0.9.

## [2.0.9]

- Previous fix for loading csv data with more attributes is fixing parsing only but this fix is knows works with files properly at FQL parser too
- Parsing `ORDER BY` clause supports default sorting by `ASC` when not specified
 
## [2.0.8]

- Fixed support for loading csv data with more attributes

```sql
SELECT * FROM [csv](file.csv, utf-8, ";").*
```

## [2.0.7]

- Fixed support for comparing data types with `IS` or `IS_NOT` operator for fql syntax

## [2.0.6]

- Fiquela conditions support compare values between row fields

## [2.0.5]

- Fixed evaluating `LIKE` operator 

## [2.0.4]

- Fixed processing of fulltext function when value of selected field is `null` 

## [2.0.3]

- Fixed tokenization when using zero compare value
- try to fix matching types when using operator `IS`

## [2.0.2]

- Rename `COMBINE` to `ARRAY_COMBINE`
- Added new function `ARRAY_MERGE` for merging two arrays
- Automatically recognize date from selected string and cast it to `\DateTimeImmutable`
- When `\DateTimeImmutable` casting to the string, it will be formatted to `c` format (`Y-m-d\TH:i:sP`)
- Added new function `DATE_FORMAT` for formatting `\DateTimeImmutable` to string

## [2.0.1]

- Fixed issue with parsing `EXCLUDE` clause 
- Improved accessor `[]->key` now supports associative arrays by wrapping them into a single-item list, allowing uniform iteration behavior.
- Replace some string by constant at sql parser
- Added new function `COMBINE` for combining two arrays

## [2.0.0]

- Package was renamed from **U**ni**Q**ue**L** to **F**i**Q**ue**L**a to better reflect what the library does
- Namespace `UQL` moved to `FQL`
- Rewritten most of the code
- Rewritten documentation
- Increased number of test and asserts from 62/304 to 171/596

### Added

#### File formats
- Added new `FQL\Stream\JsonStream` class allows parsing JSON data as a stream
- Added new `FQL\Stream\NDJson` class allows parsing [NDJSON](https://github.com/ndjson/ndjson-spec) data as a stream

#### Functions
- Added support for `DISTINCT` clause
- Added support for `EXCLUDE` clause usable at `SELECT` statement
- Added support for creating own functions for [Fluent API](docs/fluent-api.md).
- Added support for grouping data by `GROUP BY` clause
- `DISTINCT` and `GROUP BY` are not compatible with each other
- Refactored `FQL\Functions` namespace folder structure
- Supports new aggregate functions: `COUNT`, `SUM`, `AVG`, `MIN`, `MAX` and `GROUP_CONCAT`
- Added new functions: `FROM_BASE64`, `TO_BASE64`, `RANDOM_STRING`, `RANDOM_BYTES` and `MATCH() AGAINST()`
- `LIKE` operator supports the same wildcards as MySQL 
- Refactored using conditions in `WHERE` and `HAVING` clauses

#### Utils
- Refactored tests namespace to psr-4 standard
- Added benchmarking tests for queries
- Extends exception base for better exception handling
- Extended documentation

### Changed

#### Results
- Refactored fetching the results
- Interface `FQL\Query\Query` removed fetching methods and replaced by `execute()` method
instead.
- Method `execute()` returns `FQL\Result\ResultProvider` object that implements missing fetching methods.
- `execute()` decide which results are used (`FQL\Result\Stream` or `FQL\Result\InMemory`) or you can specify it manually.
- `FQL\Results\ResultProvider` knows use these functions `COUNT`, `SUM`, `AVG`, `MIN`, and `MAX`

#### Helpers and Traits
- Helpers are refactored as traits
  - `FQL\Helpers\ArrayHelper` moved to `FQL\Traits\Helpers\NestedArrayAccessor`
  - `FQL\Helpers\StringHelper` moved to `FQL\Traits\Helpers\StringOperations`

#### SQL like syntax
- Extends SQL parser to support new functionalities
- Support select functions like `COUNT`, `SUM`, `AVG`, `MIN`, `MAX` and `COALESCE` and others are usable in `SELECT` clause
- Support `EXCLUDE` clause
- Support `MATCH() AGAINST()` function for full-text search
- Support `DISTINCT` clause
- Support `GROUP BY` clause with more fields at once
- Support `HAVING` clause
- Support `OFFSET` clause
- Newly support `ORDER BY` more fields at once
- `FQL\Sql\Sql` parser knows set the base path for using FileQuery syntax

#### Debugger
- `FQL\Helpers\Debugger` moved to `FQL\Query\Debugger`
- Method `end()` renamed to `split()`
- Method `finish()` renamed to `end()`
- Added SQL syntax highlighting, just use `FQL\Query\Debugger::highlightSQL($sql)` method
- Edits single line output

### Issues
- Finally fixed issue with grouping `WHERE` or `HAVING` conditions
- Fixed issue when splitting time in `Query\Debugger`

---

_Note: The changelog begins with version 2.0.0. Older changes are not included in this document._
