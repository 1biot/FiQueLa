# Changelog

## [2.4.1]

### Fixed
- Fixed issue with parsing values for `IN` and `NOT IN` operators when using FQL syntax
- Fixed processing parameters for `ADD`, `SUB`, `MULTIPLY`, `DIVIDE` functions

## [2.4.0]

### Added
- Added code coverage report
- [FiQuela CLI](https://github.com/1biot/fiquela-cli) is part of the main repository now
- Added new function `FROM_UNIXTIME` for converting unix timestamp
- Added variadic math functions: `ADD`, `SUB`, `MULTIPLY`, `DIVIDE` supporting both field references and literal values (strings) in fluent API

### Changed
- Remove support for php 8.1 and added support for php 8.5

### Fixed
- Fixed issue with selecting all fields using `SELECT *` and preserving order of fields
- Fixed memory efficiency of **XLS** and **XLSX** file parsing by using stream reading

## [2.3.1]

### Added
- Added `DISTINCT` support for `COUNT`, `SUM`, `MIN`, `MAX`, and `GROUP_CONCAT` aggregate functions.

### Changed
- `selectAll()`/`SELECT *` can be combined with explicit field selections, matching MySQL behavior.

## [2.3.0]

### Improved
- Improved applying `GROUP BY` clause to incrementally grouped data sets which are more memory efficient
- Upgraded PHPStan level to 8

### Added

- Added new functions
  - `CAST` for type casting
  - `STR_TO_DATE` to parse a string into a date based on a specified format.
- Added more tests and asserts for existing functions and features

## [2.2.0]

### Added

- Added support for **XLS** and **XLSX** files.
- Added 7 new functions
  - `REPLACE` function for replacing occurrences of a substring within a string.
  - `COL_SPLIT` for splitting a column into multiple columns based on a delimiter.
  - `DATE_ADD` for adding a specified interval to a date.
  - `DATE_SUB` for subtracting a specified interval from a date.
  - `DAY` for extracting the day from a date.
  - `MONTH` for extracting the month from a date.
  - `YEAR` for extracting the year from a date.
- Added big amount of missing tests

## [2.1.4]

### Fixed

- Fixed loading of zero depth xml files.
- Fixed bad parsing delimiter for XML files.
- Updated using deprecated method `createFromPath` to method `from` at `\FQL\Stream\CsvProvider` class.

## [2.1.3]

### Fixed

- Fixed number parsing to handle formatted decimals and reject malformed values with trailing separators.
- Fixed parsing arguments of the functions. Knows separate arguments correctly instead of using simple `explode()` function.

### Added

- Added support for `BETWEEN` and `NOT BETWEEN` operator in `WHERE` and `HAVING` clauses.
  And also in `IF` and `CASE WHEN ...` statements.
- Added `SUBSTRING` function for extracting a substring from a string.
- Added `LOCATE` function for finding the position of a substring within a string.

## [2.1.2]

### Added

- Support for parentheses in `WHERE` and `HAVING` clauses. It is good sign for future because it will be possible to use
  complex conditions for next functions and statements, for example `JOIN ... ON`, `IF`, `CASE WHEN ...`,
  `ISNULL` and `IFNULL` and more functions in the future
- More tests for date formating
- 7 new functions
  - `CURDATE` for getting current date
  - `CURTIME` for getting current time
  - `CURRENT_TIMESTAMP` for getting current unix timestamp
  - `NOW` for getting current date and time
  - All these functions are parameter `bool $numeric` to say if you want to return numeric value or string
  - `DATE_DIFF` for getting difference between two dates in days
  - `LPAD` for left padding string with another string
  - `RPAD` for right padding string with another string

### Fixed

- Fixed the behaviour for selecting nested values from already created columns structures

## [2.1.1]

### Added

- Support for `FULL JOIN`
- Support for using `CASE` statement in `SELECT` clause
- Support for using `ISNULL` function in `SELECT` clause
- Support for own fields in `SELECT` clause. Previous solution was working only with `CONCAT`
function (`SELECT CONCAT("my own value") AS myOwnField`). Values in quotes are cast from string to according type:
  - `"1"` -> `1`
  - `"1.0"` -> `1.0`
  - `"true"` -> `true`
  - `"false"` -> `false`
  - `"null"` -> `null`
  - `"2025-05-14 12:00:00"` -> `\DateTimeImmutable`
  - `"whatever string"` -> `whatever string`

```sql
SELECT
    "my own value" AS myOwnField,
    "1" AS one,
    "1.0" AS floatNumber
```

### Fixed
 
- The value for the selected field is also retrieved from the result item, so this example now works too:
```sql
SELECT
    explode(column, "|") AS myNewColumn,
    myNewColumn[0] as myNewFirstValueOfColumn
```

### Changed

- Removed direct support converting datetime string to `\DateTimeImmutable`.
  Function `DATE_FORMAT` knows convert string to `\DateTimeImmutable` itself.
  Cast to `DateTimeImmutable` will be possible in future by `CAST` statement or other function, it is not done yet

## [2.1.0]

### Added

- supports for `RIGHT JOIN`
- 3 new functions
  - `IF`
  - `IFNULL`
  - `ARRAY_FILTER`
- new directory provider `FQL\Stream\Dir` for reading content from directory supporting all FiQueLa features
- FQL supports commented lines and multi-line comments. These comments are ignored during parsing.
  - Single-line comments start with `#` or `--`
  - Multi-line comments are enclosed in `/* ... */`

### Changed

- Improved `FQL\Sql\SqlLexer` for better tokenization
- Changed regex for FQL tokenizer for supports dot path access as one token
- Refactored `FQL\Traits\Helpers\NestedArrayAccessor` and created new `FQL\Traits\Helpers\EnhancedNestedArrayAccessor` trait

Supports:
 - Standard access: `a.b.c`
 - Indexed access: `a.b.0.c`
 - Iterated access: `a.b[].c.d`
 - Escaped keys: \`key.with.dot\`, \`key with space\`
 - Indexed access into scalar via [index] if scalar is wrapped (e.g., x[0])

## [2.0.16]

- Improve `LENGTH` function and extends support to non-string values
- Fixed FQL parsing utf-8 special chars

## [2.0.15]

- Improved FQL parser to parse parameters for `IN` and `NOT IN` operators

## [2.0.14]

- Finally fixed and tested issue with generating provide sources from FileQuery
- Conditions are now evaluated left-part non-existence fields like `null` values

## [2.0.13]

- Fixed generating providing source for CSV files

## [2.0.12]

- interface `FQL\Interface\Query` has been extended with new method `provideFileQuery(): FQL\Query\FileQuery`. It is useful for
  getting parsed information about stream.

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
