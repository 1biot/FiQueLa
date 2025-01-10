# Changelog

## [2.0.0] - UNRELEASED
### Added

#### File formats
- Added new `UQL\Stream\JsonStream` class allows parsing JSON data as a stream

#### Functions
- Added support for grouping data by `GROUP BY` clause
- Refactored Functions namespace folder structure
- Supports new aggregate functions: `COUNT`, `SUM`, `AVG`, `MIN`, `MAX` and `GROUP_CONCAT`
  - these methods are usable in `SELECT` clause and in `UQL\Results\ResultProvider` object
- Added new functions: `FROM_BASE64`, `TO_BASE64`, `RANDOM_STRING` and `RANDOM_BYTES`

#### Utils
- Added benchmarking tests for queries
- Refactored tests namespace to psr-4 standard
- Added new tests for new features
- Extends exception base for better exception handling
- Extended documentation

### Changed

#### Results
- Refactored fetching the results. Interface `UQL\Query\Query` removed fetching methods and replaced by `execute()` method
instead. This method returns `UQL\Result\ResultProvider` object that implements missing fetching methods.

#### Helpers and Traits
- Helpers are refactored as traits
  - `UQL\Helpers\ArrayHelper` moved to `UQL\Traits\Helpers\NestedArrayAccessor`
  - `UQL\Helpers\StringHelper` moved to `UQL\Traits\Helpers\StringOperations`

#### Debugger
- `UQL\Helpers\Debugger` moved to `UQL\Query\Debugger` and edits single line output
- Fixed issue when splitting time a renamed method from `end()` to `split()` and `finish()` to `end()`
- Edits single line output

### Fixed
- Fixed issue with conditions grouping in `WHERE` clause

---

_Note: The changelog begins with version 2.0.0. Older changes are not included in this document._
