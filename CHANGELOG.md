# Changelog

## [2.0.0] - UNRELEASED

Package was renamed from **U**ni**Q**ue**L** to **F**i**Q**ue**L**a to better reflect what the library does. Namespace
`UQL` moved to `FQL`

### Added

#### File formats
- Added new `FQL\Stream\JsonStream` class allows parsing JSON data as a stream

#### Functions
- Added support for `DISTINCT` clause
- Added support for grouping data by `GROUP BY` clause
- `DISTINCT` and `GROUP BY` are not compatible with each other
- Refactored `FQL\Functions` namespace folder structure
- Supports new aggregate functions: `COUNT`, `SUM`, `AVG`, `MIN`, `MAX` and `GROUP_CONCAT`
  - these methods are usable in `SELECT` clause and in `FQL\Results\ResultProvider` object
- Added new functions: `FROM_BASE64`, `TO_BASE64`, `RANDOM_STRING` and `RANDOM_BYTES`

#### Utils
- Added benchmarking tests for queries
- Refactored tests namespace to psr-4 standard
- Added new tests for new features
- Extends exception base for better exception handling
- Extended documentation

### Changed

#### Results
- Refactored fetching the results
- Interface `FQL\Query\Query` removed fetching methods and replaced by `execute()` method
instead. 
- Method `execute()` returns `FQL\Result\ResultProvider` object that implements missing fetching methods.
- `execute()` decide which results are used (`FQL\Result\Stream` or `FQL\Result\InMemory`) or you can specify it manually.

#### Helpers and Traits
- Helpers are refactored as traits
  - `FQL\Helpers\ArrayHelper` moved to `FQL\Traits\Helpers\NestedArrayAccessor`
  - `FQL\Helpers\StringHelper` moved to `FQL\Traits\Helpers\StringOperations`

#### Debugger
- `FQL\Helpers\Debugger` moved to `FQL\Query\Debugger`
- Fixed issue when splitting time
- Method `end()` renamed to `split()`
- Method `finish()` renamed to `end()`
- Edits single line output

### Fixed
- Fixed issue with conditions grouping in `WHERE` clause (no done yet)

---

_Note: The changelog begins with version 2.0.0. Older changes are not included in this document._
