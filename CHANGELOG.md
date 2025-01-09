# Changelog

## [2.0.0] - UNRELEASED
### Added
- Added new `UQL\Stream\JsonStream` class allows parsing JSON data as a stream
- Added support for grouping data by `GROUP BY` clause
- Supports new aggregate functions: `COUNT`, `SUM`, `AVG`, `MIN`, `MAX` and `GROUP_CONCAT`
- Added benchmarking tests for queries
- Added new tests for new features
- Extends exception base for better exception handling
- Extended documentation

### Changed
- Changed fetching the results. Interface `UQL\Query\Query` removed fetching methods and replaced by `execute()` method instead returns `UQL\Result\ResultProvider` object that implements fetching methods
- Refactored tests for better testing

### Fixed
- Fixed issue with conditions grouping in `WHERE` clause

---

_Note: The changelog begins with version 2.0.0. Older changes are not included in this document._
