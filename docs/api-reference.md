# API Reference

- [Enums](#enums)
  - [Format](#format)
  - [Fulltext](#fulltext)
  - [Join](#join)
  - [LogicalOperator](#logicaloperator)
  - [Operator](#operator)
  - [Sort](#sort)
  - [Type](#type)
- [Exceptions](#exceptions)
- [Interfaces](#interfaces)
  - [Aggregable](#aggregable)
  - [IncrementalAggregate](#incrementalaggregate)
  - [Invokable](#invokable)
  - [InvokableAggregate](#invokableaggregate)
  - [InvokableByReference](#invokablebyreference)
  - [InvokableNoField](#invokablenofield)
  - [JoinHashmap](#joinhashmap)
  - [Parser](#parser)
  - [Query](#query)
  - [Results](#results)
  - [Stream](#stream)
  - [Writer](#writer)
- [Query](#query-1)
  - [Provider](#provider)
  - [Query](#query-class)
  - [FileQuery](#filequery)
  - [Debugger](#debugger)
  - [TestProvider](#testprovider)
- [Results](#results-1)
  - [ResultsProvider](#resultsprovider)
  - [Stream](#stream-result)
  - [InMemory](#inmemory)
  - [DescribeResult](#describeresult)
  - [ExplainCollector](#explaincollector)
- [Stream Providers](#stream-providers)
  - [Provider](#stream-provider)
  - [AbstractStream](#abstractstream)
  - [Csv](#csv)
  - [Json](#json)
  - [JsonStream](#jsonstream)
  - [NDJson](#ndjson)
  - [Xml](#xml)
  - [Yaml](#yaml)
  - [Neon](#neon)
  - [Xls](#xls)
  - [Ods](#ods)
  - [AccessLog](#accesslog)
  - [Dir](#dir)
- [Writers](#writers)
  - [WriterFactory](#writerfactory)
  - [CsvWriter](#csvwriter)
  - [JsonWriter](#jsonwriter)
  - [NdJsonWriter](#ndjsonwriter)
  - [XmlWriter](#xmlwriter)
  - [XlsxWriter](#xlsxwriter)
  - [OdsWriter](#odswriter)
- [SQL](#sql)
  - [Sql](#sql-class)
- [Conditions](#conditions)
  - [Condition](#condition)
  - [SimpleCondition](#simplecondition)
  - [GroupCondition](#groupcondition)
  - [WhereConditionGroup](#whereconditiongroup)
  - [HavingConditionGroup](#havingconditiongroup)
- [Functions](#functions)
  - [Core](#core)
  - [Aggregate](#aggregate)
  - [Math](#math)
  - [String](#string-functions)
  - [Utils](#utils)
  - [Hashing](#hashing)
- [Traits](#traits)
  - [Select](#select)
  - [Conditions](#conditions-trait)
  - [From](#from)
  - [Into](#into)
  - [Joinable](#joinable)
  - [Groupable](#groupable)
  - [Sortable](#sortable)
  - [Limit](#limit)
  - [Unionable](#unionable)
  - [Explain](#explain)
  - [Describable](#describable)
  - [Helpers](#helpers)
- [Utils](#utils-1)
  - [InMemoryHashmap](#inmemoryhashmaputil)
  - [FileQueryPathValidator](#filequerypath-validator)

---

## Enums

**namespace:** `FQL\Enum`

### Format

`FQL\Enum\Format` _(string backed)_

| Case | Value |
|------|-------|
| **CSV** | `csv` |
| **JSON** | `json` |
| **JSON_STREAM** | `jsonFile` |
| **ND_JSON** | `ndjson` |
| **NEON** | `neon` |
| **XML** | `xml` |
| **YAML** | `yaml` |
| **XLS** | `xlsx` |
| **ODS** | `ods` |
| **LOG** | `log` |
| **DIR** | `dir` |

_public_ **getFormatProviderClass():** `class-string`

_public_ **openFile(**_string_ `$path`**):** `Interface\Stream`

_public_ **fromString(**_string_ `$data`**):** `Interface\Stream`

_public static_ **fromExtension(**_string_ `$extension`**):** `self`

_public_ **getDefaultParams():** `array`

_public_ **validateParams(**_array_ `$params`**):** `void`

_public_ **normalizeParams(**_array_ `$positional`**,** _array_ `$named`**):** `array`

### Fulltext

`FQL\Enum\Fulltext` _(string backed)_

| Case | Value |
|------|-------|
| **NATURAL** | `NATURAL` |
| **BOOLEAN** | `BOOLEAN` |

_public_ **calculate(**_string_ `$fieldValue`**,** _array_ `$terms`**):** `float`

### Join

`FQL\Enum\Join` _(string backed)_

| Case | Value |
|------|-------|
| **INNER** | `INNER JOIN` |
| **LEFT** | `LEFT JOIN` |
| **RIGHT** | `RIGHT JOIN` |
| **FULL** | `FULL JOIN` |

### LogicalOperator

`FQL\Enum\LogicalOperator` _(string backed)_

| Case | Value |
|------|-------|
| **AND** | `AND` |
| **OR** | `OR` |
| **XOR** | `XOR` |

_public_ **evaluate(**_?bool_ `$left`**,** _bool_ `$right`**):** `bool`

_public_ **render(**_bool_ `$spaces = false`**):** `string`

_public static_ **casesValues():** `string[]`

### Operator

`FQL\Enum\Operator` _(string backed)_

| Case | Value |
|------|-------|
| **EQUAL** | `=` |
| **EQUAL_STRICT** | `==` |
| **NOT_EQUAL** | `!=` |
| **NOT_EQUAL_STRICT** | `!==` |
| **GREATER_THAN** | `>` |
| **GREATER_THAN_OR_EQUAL** | `>=` |
| **LESS_THAN** | `<` |
| **LESS_THAN_OR_EQUAL** | `<=` |
| **IN** | `IN` |
| **NOT_IN** | `NOT IN` |
| **LIKE** | `LIKE` |
| **NOT_LIKE** | `NOT LIKE` |
| **REGEXP** | `REGEXP` |
| **NOT_REGEXP** | `NOT REGEXP` |
| **IS** | `IS` |
| **NOT_IS** | `IS NOT` |
| **BETWEEN** | `BETWEEN` |
| **NOT_BETWEEN** | `NOT BETWEEN` |

_public_ **evaluate(**_mixed_ `$left`**,** _mixed_ `$right`**):** `bool`

_public_ **render(**_mixed_ `$value`**,** _mixed_ `$right`**):** `string`

_public static_ **fromOrFail(**_string_ `$operator`**):** `self`

### Sort

`FQL\Enum\Sort` _(string backed)_

| Case | Value |
|------|-------|
| **ASC** | `ASC` |
| **DESC** | `DESC` |

### Type

`FQL\Enum\Type` _(string backed)_

| Case | Value |
|------|-------|
| **BOOLEAN** | `boolean` |
| **TRUE** | `TRUE` |
| **FALSE** | `FALSE` |
| **NUMBER** | `number` |
| **INTEGER** | `integer` |
| **FLOAT** | `double` |
| **STRING** | `string` |
| **NULL** | `NULL` |
| **ARRAY** | `array` |
| **OBJECT** | `object` |
| **RESOURCE** | `resource` |
| **RESOURCE_CLOSED** | `resource (closed)` |
| **UNKNOWN** | `unknown type` |

_public_ **castValue(**_mixed_ `$value`**,** _?Type_ `$type = null`**):** `mixed`

_public static_ **match(**_mixed_ `$value`**):** `self`

_public static_ **matchByString(**_string_ `$value`**):** `mixed`

_public static_ **listValues():** `Type[]`

---

## Exceptions

**namespace:** `FQL\Exception`

| Exception | Extends |
|-----------|---------|
| `Exception` | `\Exception` |
| `InvalidArgumentException` | `\InvalidArgumentException` |
| `UnexpectedValueException` | `\UnexpectedValueException` |
| `QueryLogicException` | `\LogicException` |
| `FileAlreadyExistsException` | `\RuntimeException` |
| `FileNotFoundException` | `Exception` |
| `FileQueryException` | `InvalidArgumentException` |
| `InvalidFormatException` | `Exception` |
| `UnableOpenFileException` | `\Exception` |
| `NotImplementedException` | `Exception` |
| `AliasException` | `InvalidArgumentException` |
| `CaseException` | `InvalidArgumentException` |
| `JoinException` | `InvalidArgumentException` |
| `OrderByException` | `InvalidArgumentException` |
| `SelectException` | `InvalidArgumentException` |
| `SortException` | `InvalidArgumentException` |

---

## Interfaces

**namespace:** `FQL\Interface`

### Aggregable

`FQL\Interface\Aggregable`

_public_ **sum(**_string_ `$key`**):** `float`

_public_ **avg(**_string_ `$key`**,** _int_ `$decimalPlaces = 2`**):** `float`

_public_ **min(**_string_ `$key`**):** `float`

_public_ **max(**_string_ `$key`**):** `float`

### IncrementalAggregate

`FQL\Interface\IncrementalAggregate`

_public_ **initAccumulator():** `mixed`

_public_ **accumulate(**_mixed_ `$accumulator`**,** _array_ `$item`**):** `mixed`

_public_ **finalize(**_mixed_ `$accumulator`**):** `mixed`

### Invokable

`FQL\Interface\Invokable`

_public_ **getName():** `string`

_public_ **__invoke(**_array_ `$item`**,** _array_ `$resultItem`**):** `mixed`

### InvokableAggregate

`FQL\Interface\InvokableAggregate`

_public_ **getName():** `string`

_public_ **__invoke(**_array_ `$items`**):** `mixed`

### InvokableByReference

`FQL\Interface\InvokableByReference`

_public_ **getName():** `string`

_public_ **__invoke(**_array_ `$item`**,** _array_ `&$resultItem`**):** `mixed`

### InvokableNoField

`FQL\Interface\InvokableNoField`

_public_ **getName():** `string`

_public_ **__invoke():** `mixed`

### JoinHashmap

`FQL\Interface\JoinHashmap`

_public_ **set(**_string|int_ `$key`**,** _array_ `$row`**):** `void`

_public_ **get(**_string|int_ `$key`**):** `array`

_public_ **getAll():** `array`

_public_ **has(**_string|int_ `$key`**):** `bool`

_public_ **getStructure():** `array`

_public_ **clear():** `void`

### Parser

`FQL\Interface\Parser`

_public_ **parseWithQuery(**_Query_ `$query`**,** _?int_ `$startPosition = null`**):** `Query`

_public_ **parse():** `Results`

### Query

`FQL\Interface\Query` _extends_ `\Stringable`

#### Constants

| Constant | Value |
|----------|-------|
| `SELECT_ALL` | `*` |
| `FROM_ALL` | `*` |
| `SELECT` | `SELECT` |
| `DESCRIBE` | `DESCRIBE` |
| `DISTINCT` | `DISTINCT` |
| `CASE` | `CASE` |
| `WHEN` | `WHEN` |
| `THEN` | `THEN` |
| `ELSE` | `ELSE` |
| `END` | `END` |
| `EXCLUDE` | `EXCLUDE` |
| `AS` | `AS` |
| `ON` | `ON` |
| `BY` | `BY` |
| `FROM` | `FROM` |
| `INTO` | `INTO` |
| `WHERE` | `WHERE` |
| `HAVING` | `HAVING` |
| `OFFSET` | `OFFSET` |
| `LIMIT` | `LIMIT` |
| `EXPLAIN` | `EXPLAIN` |
| `ANALYZE` | `ANALYZE` |
| `PER_PAGE_DEFAULT` | `10` |

#### Selection

_public_ **select(**_string_ `...$fields`**):** `Query`

_public_ **selectAll():** `Query`

_public_ **distinct(**_bool_ `$distinct = true`**):** `Query`

_public_ **exclude(**_string_ `...$fields`**):** `Query`

_public_ **as(**_string_ `$alias`**):** `Query`

#### String functions

_public_ **concat(**_string_ `...$fields`**):** `Query`

_public_ **concatWithSeparator(**_string_ `$separator`**,** _string_ `...$fields`**):** `Query`

_public_ **lower(**_string_ `$field`**):** `Query`

_public_ **upper(**_string_ `$field`**):** `Query`

_public_ **reverse(**_string_ `$field`**):** `Query`

_public_ **explode(**_string_ `$field`**,** _string_ `$separator = ','`**):** `Query`

_public_ **split(**_string_ `$field`**,** _string_ `$separator = ','`**):** `Query`

_public_ **implode(**_string_ `$field`**,** _string_ `$separator = ','`**):** `Query`

_public_ **glue(**_string_ `$field`**,** _string_ `$separator = ','`**):** `Query`

_public_ **substring(**_string_ `$field`**,** _int_ `$start`**,** _?int_ `$length = null`**):** `Query`

_public_ **locate(**_string_ `$substring`**,** _string_ `$field`**,** _?int_ `$position = null`**):** `Query`

_public_ **leftPad(**_string_ `$field`**,** _int_ `$length`**,** _string_ `$padString = ' '`**):** `Query`

_public_ **rightPad(**_string_ `$field`**,** _int_ `$length`**,** _string_ `$padString = ' '`**):** `Query`

_public_ **replace(**_string_ `$field`**,** _string_ `$search`**,** _string_ `$replace`**):** `Query`

_public_ **fromBase64(**_string_ `$field`**):** `Query`

_public_ **toBase64(**_string_ `$field`**):** `Query`

_public_ **randomString(**_int_ `$length = 10`**):** `Query`

_public_ **fulltext(**_array_ `$fields`**,** _string_ `$searchQuery`**):** `Query`

_public_ **matchAgainst(**_array_ `$fields`**,** _string_ `$searchQuery`**,** _?Fulltext_ `$mode = null`**):** `Query`

#### Math functions

_public_ **round(**_string_ `$field`**,** _int_ `$precision = 0`**):** `Query`

_public_ **ceil(**_string_ `$field`**):** `Query`

_public_ **floor(**_string_ `$field`**):** `Query`

_public_ **modulo(**_string_ `$field`**,** _int_ `$divisor`**):** `Query`

_public_ **add(**_string_ `...$fields`**):** `Query`

_public_ **subtract(**_string_ `...$fields`**):** `Query`

_public_ **multiply(**_string_ `...$fields`**):** `Query`

_public_ **divide(**_string_ `...$fields`**):** `Query`

#### Hashing functions

_public_ **sha1(**_string_ `$field`**):** `Query`

_public_ **md5(**_string_ `$field`**):** `Query`

#### Utility functions

_public_ **coalesce(**_string_ `...$fields`**):** `Query`

_public_ **coalesceNotEmpty(**_string_ `...$fields`**):** `Query`

_public_ **length(**_string_ `$field`**):** `Query`

_public_ **cast(**_string_ `$field`**,** _Type_ `$as`**):** `Query`

_public_ **randomBytes(**_int_ `$length = 10`**):** `Query`

_public_ **uuid():** `Query`

_public_ **arrayCombine(**_string_ `$keysArrayField`**,** _string_ `$valueArrayField`**):** `Query`

_public_ **arrayMerge(**_string_ `$keysArrayField`**,** _string_ `$valueArrayField`**):** `Query`

_public_ **arrayFilter(**_string_ `$field`**):** `Query`

_public_ **arraySearch(**_string_ `$field`**,** _string_ `$value`**):** `Query`

_public_ **colSplit(**_string_ `$field`**,** _?string_ `$format = null`**,** _?string_ `$keyField = null`**):** `Query`

#### Date functions

_public_ **strToDate(**_string_ `$valueField`**,** _string_ `$format`**):** `Query`

_public_ **formatDate(**_string_ `$dateField`**,** _string_ `$format = 'c'`**):** `Query`

_public_ **fromUnixTime(**_string_ `$dateField`**,** _string_ `$format = 'c'`**):** `Query`

_public_ **currentDate(**_bool_ `$numeric = false`**):** `Query`

_public_ **currentTime(**_bool_ `$numeric = false`**):** `Query`

_public_ **currentTimestamp():** `Query`

_public_ **now(**_bool_ `$numeric = false`**):** `Query`

_public_ **dateDiff(**_string_ `$date`**,** _string_ `$secondDate`**):** `Query`

_public_ **dateAdd(**_string_ `$date`**,** _string_ `$interval`**):** `Query`

_public_ **dateSub(**_string_ `$date`**,** _string_ `$interval`**):** `Query`

_public_ **year(**_string_ `$dateField`**):** `Query`

_public_ **month(**_string_ `$dateField`**):** `Query`

_public_ **day(**_string_ `$dateField`**):** `Query`

#### Conditional functions

_public_ **if(**_string_ `$conditionString`**,** _string_ `$trueStatement`**,** _string_ `$falseStatement`**):** `Query`

_public_ **ifNull(**_string_ `$field`**,** _string_ `$trueStatement`**):** `Query`

_public_ **isNull(**_string_ `$field`**):** `Query`

_public_ **case():** `Query`

_public_ **whenCase(**_string_ `$conditionString`**,** _string_ `$thenStatement`**):** `Query`

_public_ **elseCase(**_string_ `$defaultCaseStatement`**):** `Query`

_public_ **endCase():** `Query`

#### Data source

_public_ **from(**_string_ `$query`**):** `Query`

_public_ **into(**_FileQuery_ `$fileQuery`**):** `static`

_public_ **hasInto():** `bool`

_public_ **getInto():** `?FileQuery`

#### Joins

_public_ **join(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

_public_ **innerJoin(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

_public_ **leftJoin(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

_public_ **rightJoin(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

_public_ **fullJoin(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

_public_ **on(**_string_ `$leftKey`**,** _Operator_ `$operator`**,** _string_ `$rightKey`**):** `Query`

#### Conditions

_public_ **where(**_string_ `$field`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Query`

_public_ **having(**_string_ `$field`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Query`

_public_ **and(**_string_ `$field`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Query`

_public_ **or(**_string_ `$field`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Query`

_public_ **xor(**_string_ `$field`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Query`

_public_ **whereGroup():** `Query`

_public_ **addWhereConditions(**_WhereConditionGroup_ `$whereConditionGroup`**):** `Query`

_public_ **havingGroup():** `Query`

_public_ **addHavingConditions(**_HavingConditionGroup_ `$havingConditionGroup`**):** `Query`

_public_ **andGroup():** `Query`

_public_ **orGroup():** `Query`

_public_ **endGroup():** `Query`

#### Aggregation

_public_ **groupBy(**_string_ `...$fields`**):** `Query`

_public_ **count(**_?string_ `$field = null`**,** _bool_ `$distinct = false`**):** `Query`

_public_ **sum(**_string_ `$field`**,** _bool_ `$distinct = false`**):** `Query`

_public_ **avg(**_string_ `$field`**):** `Query`

_public_ **min(**_string_ `$field`**,** _bool_ `$distinct = false`**):** `Query`

_public_ **max(**_string_ `$field`**,** _bool_ `$distinct = false`**):** `Query`

_public_ **groupConcat(**_string_ `$field`**,** _string_ `$separator = ','`**,** _bool_ `$distinct = false`**):** `Query`

#### Sorting

_public_ **orderBy(**_string_ `$field`**,** _Sort_ `$direction = Sort::ASC`**):** `Query`

_public_ **sortBy(**_string_ `$field`**,** _Sort_ `$direction = Sort::ASC`**):** `Query`

_public_ **asc():** `Query`

_public_ **desc():** `Query`

#### Pagination

_public_ **offset(**_int_ `$offset`**):** `Query`

_public_ **limit(**_int_ `$limit`**,** _?int_ `$offset = null`**):** `Query`

_public_ **page(**_int_ `$page`**,** _int_ `$perPage = PER_PAGE_DEFAULT`**):** `Query`

#### Execution

_public_ **execute(**_?string_ `$resultClass = null`**):** `ResultsProvider`

_public_ **describe():** `static`

_public_ **explain():** `Query`

_public_ **explainAnalyze():** `Query`

#### Union

_public_ **union(**_Query_ `$query`**):** `Query`

_public_ **unionAll(**_Query_ `$query`**):** `Query`

#### Metadata

_public_ **provideFileQuery():** `FileQuery`

### Results

`FQL\Interface\Results` _extends_ `\Countable`

_public_ **fetchAll(**_?string_ `$dto = null`**):** `\Generator`

_public_ **fetch(**_?string_ `$dto = null`**):** `mixed`

_public_ **fetchSingle(**_string_ `$key`**):** `mixed`

_public_ **fetchNth(**_int|string_ `$n`**,** _?string_ `$dto = null`**):** `\Generator`

_public_ **exists():** `bool`

### Stream

`FQL\Interface\Stream`

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

_public_ **getStream(**_?string_ `$query`**):** `\ArrayIterator`

_public_ **getStreamGenerator(**_?string_ `$query`**):** `\Generator`

_public_ **provideSource():** `string`

_public_ **query():** `Query`

_public_ **fql(**_string_ `$sql`**):** `Results`

### Writer

`FQL\Interface\Writer`

_public_ **write(**_array_ `$row`**):** `void`

_public_ **close():** `void`

_public_ **getFileQuery():** `FileQuery`

---

## Query

**namespace:** `FQL\Query`

### Provider

`FQL\Query\Provider`

_public static_ **fromFile(**_string_ `$path`**,** _?Format_ `$format = null`**):** `Interface\Query`

Creates a query from a file path. Format is auto-detected from extension if not specified.

_public static_ **fromFileQuery(**_string_ `$fileQuery`**):** `Interface\Query`

Creates a query from a FileQuery string (e.g. `json(file.json).data.products`).

_public static_ **fql(**_string_ `$sql`**):** `Interface\Query`

Creates a query from an FQL SQL string.

### Query (class)

`FQL\Query\Query` _implements_ `Interface\Query`

Uses traits: `Select`, `Conditions`, `From`, `Into`, `Joinable`, `Groupable`, `Sortable`, `Limit`, `Unionable`, `Explain`, `Describable`

_public_ **__construct(**_Interface\Stream_ `$stream`**)**

All methods are inherited from `Interface\Query` via traits. See [Interface\Query](#query) for the full method list.

### FileQuery

`FQL\Query\FileQuery` _implements_ `\Stringable`

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$format` | `?string` | Format name (e.g. `csv`, `json`, `xml`) |
| `$extension` | `?Format` | Resolved `Format` enum |
| `$file` | `?string` | Absolute or relative file path |
| `$params` | `array` | Additional parameters |
| `$query` | `?string` | Dotted path to data (e.g. `data.products`) |

#### Methods

_public_ **__construct(**_string_ `$queryPath`**)**

Parses a FileQuery string like `json(./data/products.json, "utf-8").data.products`.

_public_ **getParam(**_string_ `$key`**,** _mixed_ `$default = null`**):** `mixed`

_public_ **withFile(**_?string_ `$file`**):** `self`

_public_ **withParam(**_string_ `$key`**,** _string_ `$value`**):** `self`

_public_ **withQuery(**_?string_ `$query`**):** `self`

_public_ **withFormat(**_?string_ `$format`**):** `self`

_public_ **withEncoding(**_string_ `$encoding`**):** `self` _(deprecated, use `withParam`)_

_public_ **withDelimiter(**_string_ `$delimiter`**):** `self` _(deprecated, use `withParam`)_

_public static_ **fromStream(**_Interface\Stream_ `$stream`**):** `self`

_public static_ **getRegexp():** `string`

_public_ **__toString():** `string`

### Debugger

`FQL\Query\Debugger`

Provides terminal-based query debugging, inspection, and benchmarking utilities.

_public static_ **start():** `void`

_public static_ **end():** `void`

_public static_ **split(**_bool_ `$header = true`**):** `void`

_public static_ **dump(**_mixed_ `$var`**):** `void`

_public static_ **inspectQuery(**_Interface\Query_ `$query`**,** _bool_ `$listResults = false`**):** `void`

_public static_ **inspectSql(**_string_ `$sql`**):** `Interface\Query`

_public static_ **inspectStreamSql(**_Interface\Stream_ `$stream`**,** _string_ `$sql`**):** `Query`

_public static_ **benchmarkQuery(**_Interface\Query_ `$query`**,** _int_ `$iterations = 2500`**):** `void`

_public static_ **echoSection(**_string_ `$text`**,** _?string_ `$color = 'cyan'`**):** `void`

_public static_ **echoLineNameValue(**_string_ `$name`**,** _string|bool|float|int|null_ `$value`**,** _int_ `$beginCharRepeat = 1`**):** `void`

_public static_ **echoLine(**_string_ `$text`**,** _int_ `$beginCharRepeat = 1`**):** `void`

_public static_ **queryToOutput(**_string_ `$query`**):** `void`

_public static_ **highlightSQL(**_string_ `$sql`**):** `string`

_public static_ **echoException(**_\Exception_ `$e`**):** `void`

_public static_ **memoryUsage(**_bool_ `$realUsage = false`**):** `string`

_public static_ **memoryPeakUsage(**_bool_ `$realUsage = false`**):** `string`

_public static_ **memoryDebug(**_int_ `$beginCharRepeat = 1`**):** `void`

### TestProvider

`FQL\Query\TestProvider` _implements_ `Interface\Query`

A test-friendly query implementation exposing internal state for assertions.

_public_ **getSelectedFields():** `array`

_public_ **getExcludedFields():** `array`

_public_ **getLimitAndOffset():** `array`

_public_ **getFromSource():** `string`

_public_ **resetConditions():** `Query`

_public_ **test():** `string`

---

## Results

**namespace:** `FQL\Results`

### ResultsProvider

`FQL\Results\ResultsProvider` _implements_ `Interface\Results`, `\IteratorAggregate` _(abstract)_

Base class for all result providers. Provides fetch, count, exists, and `into()` export.

_public_ **fetchAll(**_?string_ `$dto = null`**):** `\Generator`

_public_ **fetch(**_?string_ `$dto = null`**):** `mixed`

_public_ **fetchSingle(**_string_ `$key`**):** `mixed`

_public_ **fetchNth(**_int|string_ `$n`**,** _?string_ `$dto = null`**):** `\Generator`

_public_ **exists():** `bool`

_public_ **into(**_FileQuery|string_ `$fileQuery`**):** `?FileQuery`

Exports results to a file. Returns the effective `FileQuery` with defaults applied, or `null`.

_abstract public_ **count():** `int`

_abstract public_ **getIterator():** `\Traversable`

### Stream (result)

`FQL\Results\Stream` _extends_ `ResultsProvider` _implements_ `Interface\Aggregable`

Generator-based streaming result set. Builds the full pipeline (stream, where, join, group, having, sort, limit) on each iteration.

_public_ **__construct(**_Interface\Stream_ `$stream`**,** _bool_ `$distinct`**,** _array_ `$selectedFields`**,** _array_ `$excludedFields`**,** _string_ `$from`**,** _BaseConditionGroup_ `$where`**,** _BaseConditionGroup_ `$havings`**,** _array_ `$joins`**,** _array_ `$groupByFields`**,** _array_ `$orderings`**,** _int|null_ `$limit`**,** _int|null_ `$offset`**,** _?FileQuery_ `$into = null`**,** _JoinHashMap_ `$joinHashMap = new InMemoryHashmap()`**,** _array_ `$unions = []`**,** _?ExplainCollector_ `$collector = null`**)**

_public_ **setCollector(**_ExplainCollector_ `$collector`**,** _string_ `$prefix = ''`**):** `void`

_public_ **setJoinHashMap(**_JoinHashMap_ `$joinHashMap`**):** `void`

_public_ **getIterator():** `\Traversable`

_public_ **count():** `int`

_public_ **sum(**_string_ `$key`**):** `float`

_public_ **avg(**_string_ `$key`**,** _int_ `$decimalPlaces = 2`**):** `float`

_public_ **min(**_string_ `$key`**):** `float`

_public_ **max(**_string_ `$key`**):** `float`

### InMemory

`FQL\Results\InMemory` _extends_ `ResultsProvider` _implements_ `Interface\Aggregable`

Array-backed in-memory result set.

_public_ **__construct(**_array_ `$results`**)**

_public_ **getIterator():** `\Traversable`

### DescribeResult

`FQL\Results\DescribeResult` _extends_ `ResultsProvider`

Single-pass schema analysis of a data source. Returns one row per column with type statistics.

_public_ **__construct(**_Stream_ `$source`**)**

_public_ **getSourceRowCount():** `int`

_public_ **getIterator():** `\Generator`

Each row yields:

| Key | Type | Description |
|-----|------|-------------|
| `column` | `string` | Column name (dot notation for nested) |
| `path` | `string[]` | Array of path segments for correct backtick quoting |
| `types` | `array` | Map of type name to occurrence count |
| `totalRows` | `int` | Non-empty row count |
| `totalTypes` | `int` | Distinct type count |
| `dominant` | `string` | Most frequent type |
| `suspicious` | `bool` | Mixed non-empty types (except int+double) |
| `confidence` | `float` | Dominant type ratio (0.0-1.0) |
| `completeness` | `float` | Non-empty ratio (0.0-1.0) |
| `constant` | `bool` | All non-empty values identical |
| `isEnum` | `bool` | 2-5 unique values |
| `isUnique` | `bool` | All non-empty values unique |

### ExplainCollector

`FQL\Results\ExplainCollector`

Collects execution plan phases and timing data for `EXPLAIN` / `EXPLAIN ANALYZE`.

_public_ **addPhase(**_string_ `$phase`**,** _string_ `$note`**,** _bool_ `$hasInput`**):** `int`

_public_ **incrementIn(**_int_ `$index`**):** `void`

_public_ **incrementOut(**_int_ `$index`**):** `void`

_public_ **setIncrementIn(**_int_ `$index`**,** _int_ `$value`**):** `void`

_public_ **setIncrementOut(**_int_ `$index`**,** _int_ `$value`**):** `void`

_public_ **startTimer(**_int_ `$index`**):** `void`

_public_ **stopTimer(**_int_ `$index`**):** `void`

_public_ **addTime(**_int_ `$index`**,** _float_ `$ms`**):** `void`

_public_ **recordMemPeak(**_int_ `$index`**):** `void`

_public_ **startAccumulator(**_int_ `$index`**):** `void`

_public_ **stopAccumulator(**_int_ `$index`**):** `void`

_public_ **finalize():** `array`

_public_ **buildPlan(**_string_ `$streamNote`**,** _bool_ `$hasJoin`**,** _array_ `$joinNotes`**,** _bool_ `$hasWhere`**,** _string_ `$whereNote`**,** _bool_ `$isGroupable`**,** _string_ `$groupNote`**,** _bool_ `$hasHaving`**,** _string_ `$havingNote`**,** _bool_ `$isSortable`**,** _string_ `$sortNote`**,** _bool_ `$isLimitable`**,** _string_ `$limitNote`**,** _array_ `$unions`**,** _?FileQuery_ `$into = null`**):** `array`

---

## Stream Providers

**namespace:** `FQL\Stream`

### Stream Provider

`FQL\Stream\Provider`

_public static_ **fromFile(**_string_ `$path`**,** _?Format_ `$format = null`**):** `Interface\Stream`

_public static_ **fromString(**_string_ `$data`**,** _Format_ `$format`**):** `Interface\Stream`

### AbstractStream

`FQL\Stream\AbstractStream` _implements_ `Interface\Stream` _(abstract)_

Base class for all stream providers. Provides `query()` and `fql()` methods.

_public_ **query():** `Interface\Query`

_public_ **fql(**_string_ `$sql`**):** `Interface\Results`

_abstract public static_ **open(**_string_ `$path`**):** `self`

_abstract public static_ **string(**_string_ `$data`**):** `self`

_abstract public_ **getStream(**_?string_ `$query`**):** `\ArrayIterator`

_abstract public_ **getStreamGenerator(**_?string_ `$query`**):** `\Generator`

_abstract public_ **provideSource():** `string`

### Csv

`FQL\Stream\Csv` _extends_ `AbstractStream`

Reads CSV files using `league/csv`. Supports encoding, custom delimiters.

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

### Json

`FQL\Stream\Json`  _extends_ `AbstractStream`

Reads entire JSON file into memory, then navigates to query path.

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

### JsonStream

`FQL\Stream\JsonStream` _extends_ `AbstractStream`

Memory-efficient streaming JSON parser for large files. Uses `halaxa/json-machine`.

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

### NDJson

`FQL\Stream\NDJson` _extends_ `AbstractStream`

Reads newline-delimited JSON (one JSON object per line).

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

### Xml

`FQL\Stream\Xml` _extends_ `AbstractStream`

Reads XML files. Query path maps to element hierarchy.

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

### Yaml

`FQL\Stream\Yaml` _extends_ `AbstractStream`

Reads YAML files using `symfony/yaml`.

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

### Neon

`FQL\Stream\Neon` _extends_ `AbstractStream`

Reads NEON files using `nette/neon`.

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

### Xls

`FQL\Stream\Xls` _extends_ `AbstractStream`

Reads XLSX spreadsheet files using `openspout/openspout`. Query: `SheetName.StartCell`.

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

### Ods

`FQL\Stream\Ods` _extends_ `AbstractStream`

Reads ODS spreadsheet files using `openspout/openspout`. Query: `SheetName.StartCell`.

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

### AccessLog

`FQL\Stream\AccessLog` _extends_ `AbstractStream`

Reads HTTP server access log files. Parses each line according to a predefined profile or a custom Apache
`log_format` pattern. File query format name: `log`.

_public static_ **open(**_string_ `$path`**):** `self`

_public_ **setFormat(**_string_ `$format`**):** `void`

Sets the active log profile. Accepts one of the predefined profile names (`nginx_combined`, `nginx_main`,
`apache_combined`, `apache_common`) or `"custom"` (requires a subsequent `setPattern()` call).
Default: `"nginx_combined"`.

_public_ **setPattern(**_string_ `$pattern`**):** `void`

Sets a custom Apache `log_format` pattern string. Only effective when the format is `"custom"`.

**Predefined profiles:**

| Profile name      | Pattern                                                                 |
|-------------------|-------------------------------------------------------------------------|
| `nginx_combined`  | `%h - %u [%t] "%r" %>s %b "%{Referer}i" "%{User-Agent}i"` _(default)_ |
| `nginx_main`      | `%h - %u [%t] "%r" %>s %b`                                             |
| `apache_combined` | `%h %l %u [%t] "%r" %>s %b "%{Referer}i" "%{User-Agent}i"`             |
| `apache_common`   | `%h %l %u [%t] "%r" %>s %b`                                            |

**Special fields in every row:**

| Field    | Type          | Description                                      |
|----------|---------------|--------------------------------------------------|
| `_raw`   | string        | Original unparsed log line                       |
| `_error` | string\|null  | `null` on success, error message on parse failure |

### Dir

`FQL\Stream\Dir` _extends_ `AbstractStream`

Reads directory contents as a stream of file metadata rows.

_public static_ **open(**_string_ `$path`**):** `self`

_public static_ **string(**_string_ `$data`**):** `self`

---

## Writers

**namespace:** `FQL\Stream\Writers`

### WriterFactory

`FQL\Stream\Writers\WriterFactory`

_public static_ **create(**_FileQuery_ `$fileQuery`**):** `Interface\Writer`

Creates a writer instance based on the FileQuery format.

### CsvWriter

`FQL\Stream\Writers\CsvWriter` _implements_ `Interface\Writer`

_public_ **__construct(**_FileQuery_ `$fileQuery`**)**

_public_ **write(**_array_ `$row`**):** `void`

_public_ **close():** `void`

_public_ **getFileQuery():** `FileQuery` — returns `*` as default query.

### JsonWriter

`FQL\Stream\Writers\JsonWriter` _implements_ `Interface\Writer`

_public_ **__construct(**_FileQuery_ `$fileQuery`**)**

_public_ **write(**_array_ `$row`**):** `void`

_public_ **close():** `void` — nests data under query path segments. `*` or `null` writes flat array.

_public_ **getFileQuery():** `FileQuery` — returns `*` as default query.

### NdJsonWriter

`FQL\Stream\Writers\NdJsonWriter` _implements_ `Interface\Writer`

_public_ **__construct(**_FileQuery_ `$fileQuery`**)**

_public_ **write(**_array_ `$row`**):** `void`

_public_ **close():** `void`

_public_ **getFileQuery():** `FileQuery` — returns `*` as default query.

### XmlWriter

`FQL\Stream\Writers\XmlWriter` _implements_ `Interface\Writer`

_public_ **__construct(**_FileQuery_ `$fileQuery`**)**

_public_ **write(**_array_ `$row`**):** `void`

_public_ **close():** `void` — query defines `ROOT.ROW` element names. Defaults to `rows.row`.

_public_ **getFileQuery():** `FileQuery` — returns `rows.row` as default query.

### XlsxWriter

`FQL\Stream\Writers\XlsxWriter` _extends_ `AbstractSpreadsheetWriter`

_public_ **__construct(**_FileQuery_ `$fileQuery`**)**

_public_ **write(**_array_ `$row`**):** `void`

_public_ **close():** `void` — query defines `SheetName.StartCell`. `*` uses first sheet from A1.

_public_ **getFileQuery():** `FileQuery`

### OdsWriter

`FQL\Stream\Writers\OdsWriter` _extends_ `AbstractSpreadsheetWriter`

_public_ **__construct(**_FileQuery_ `$fileQuery`**)**

_public_ **write(**_array_ `$row`**):** `void`

_public_ **close():** `void` — query defines `SheetName.StartCell`. `*` uses first sheet from A1.

_public_ **getFileQuery():** `FileQuery`

---

## SQL

**namespace:** `FQL\Sql`

### Sql (class)

`FQL\Sql\Sql` _extends_ `SqlLexer` _implements_ `Interface\Parser`

Parses FQL SQL strings into query objects.

_public_ **__construct(**_string_ `$sql`**,** _?string_ `$basePath = null`**)**

_public_ **parse():** `Interface\Results`

Parses and executes the FQL SQL, returning results directly.

_public_ **toQuery(**_?int_ `$startPosition = null`**):** `Interface\Query`

Parses the FQL SQL into a query object without executing it.

_public_ **parseWithQuery(**_Interface\Query_ `$query`**,** _?int_ `$startPosition = null`**):** `Interface\Query`

Applies SQL clause tokens to an existing query object.

---

## Conditions

**namespace:** `FQL\Conditions`

### Condition

`FQL\Conditions\Condition` _(abstract)_

| Constant | Value |
|----------|-------|
| `WHERE` | `where` |
| `HAVING` | `having` |

_public_ **__construct(**_LogicalOperator_ `$logicalOperator`**)**

_abstract public_ **evaluate(**_array_ `$item`**,** _bool_ `$nestingValues`**):** `bool`

_abstract public_ **render():** `string`

### SimpleCondition

`FQL\Conditions\SimpleCondition` _extends_ `Condition`

A single condition: `field operator value`.

_public_ **__construct(**_LogicalOperator_ `$logicalOperator`**,** _string_ `$field`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**)**

_public_ **evaluate(**_array_ `$item`**,** _bool_ `$nestingValues`**):** `bool`

_public_ **render():** `string`

### GroupCondition

`FQL\Conditions\GroupCondition` _extends_ `Condition` _implements_ `\Countable`

A group of conditions joined by logical operators. Supports nesting.

_public_ **__construct(**_LogicalOperator_ `$logicalOperator`**,** _?GroupCondition_ `$parent = null`**)**

_public_ **addCondition(**_LogicalOperator_ `$logicalOperator`**,** _Condition_ `$condition`**):** `void`

_public_ **getConditions():** `array`

_public_ **getParent():** `?GroupCondition`

_public_ **evaluate(**_array_ `$item`**,** _bool_ `$nestingValues`**):** `bool`

_public_ **render():** `string`

_public_ **count():** `int`

_public_ **getDepth():** `int`

### WhereConditionGroup

`FQL\Conditions\WhereConditionGroup` _extends_ `BaseConditionGroup`

Root condition group for `WHERE` clauses.

### HavingConditionGroup

`FQL\Conditions\HavingConditionGroup` _extends_ `BaseConditionGroup`

Root condition group for `HAVING` clauses.

---

## Functions

**namespace:** `FQL\Functions`

### Core

Base classes for building query functions.

| Class | Extends | Description |
|-------|---------|-------------|
| `BaseFunction` | — | Base for single-invocation functions (`Invokable`) |
| `BaseFunctionByReference` | — | Base for functions that modify result row by reference (`InvokableByReference`) |
| `SingleFieldFunction` | `BaseFunction` | Function operating on a single field |
| `SingleFieldFunctionByReference` | `BaseFunctionByReference` | By-reference function on a single field |
| `MultipleFieldsFunction` | `BaseFunction` | Function operating on multiple fields |
| `NoFieldFunction` | — | Function with no field input (`InvokableNoField`) |
| `AggregateFunction` | — | Base for aggregate functions (`InvokableAggregate`, `IncrementalAggregate`) |
| `SingleFieldAggregateFunction` | `AggregateFunction` | Aggregate function on a single field with DISTINCT support |

### Aggregate

| Class | SQL | Description |
|-------|-----|-------------|
| `Avg` | `AVG(field)` | Average value |
| `Count` | `COUNT(field)` | Row count |
| `GroupConcat` | `GROUP_CONCAT(field, sep)` | Concatenate values |
| `Max` | `MAX(field)` | Maximum value |
| `Min` | `MIN(field)` | Minimum value |
| `Sum` | `SUM(field)` | Sum values |

### Math

| Class | SQL | Description |
|-------|-----|-------------|
| `Add` | `ADD(a, b, ...)` | Add numbers |
| `Ceil` | `CEIL(field)` | Round up |
| `Divide` | `DIVIDE(a, b, ...)` | Divide numbers |
| `Floor` | `FLOOR(field)` | Round down |
| `Mod` | `MOD(field, divisor)` | Modulo |
| `Multiply` | `MULTIPLY(a, b, ...)` | Multiply numbers |
| `Round` | `ROUND(field, precision)` | Round |
| `Sub` | `SUB(a, b, ...)` | Subtract numbers |

### String functions

| Class | SQL | Description |
|-------|-----|-------------|
| `Base64Decode` | `BASE64_DECODE(field)` | Decode base64 |
| `Base64Encode` | `BASE64_ENCODE(field)` | Encode to base64 |
| `Concat` | `CONCAT(a, b, ...)` | Concatenate without separator |
| `ConcatWS` | `CONCAT_WS(sep, a, b, ...)` | Concatenate with separator |
| `Explode` | `EXPLODE(field, sep)` | Split string to array |
| `Fulltext` | `MATCH(...) AGAINST(...)` | Fulltext search score |
| `Implode` | `IMPLODE(field, sep)` | Join array to string |
| `LeftPad` | `LPAD(field, len, char)` | Left pad |
| `Locate` | `LOCATE(substr, field)` | Find position |
| `Lower` | `LOWER(field)` | Lowercase |
| `RandomString` | `RANDOM_STRING(len)` | Random string |
| `Replace` | `REPLACE(field, search, rep)` | Replace substring |
| `Reverse` | `REVERSE(field)` | Reverse string |
| `RightPad` | `RPAD(field, len, char)` | Right pad |
| `Substring` | `SUBSTRING(field, start, len)` | Extract substring |
| `Upper` | `UPPER(field)` | Uppercase |

### Utils

| Class | SQL | Description |
|-------|-----|-------------|
| `ArrayCombine` | `ARRAY_COMBINE(keys, values)` | Combine arrays |
| `ArrayFilter` | `ARRAY_FILTER(field)` | Filter empty values |
| `ArrayMerge` | `ARRAY_MERGE(a, b)` | Merge arrays |
| `ArraySearch` | `ARRAY_SEARCH(field, needle)` | Search in array |
| `Cast` | `CAST(field AS type)` | Type cast |
| `Coalesce` | `COALESCE(a, b, ...)` | First non-null |
| `CoalesceNotEmpty` | `COALESCE_NE(a, b, ...)` | First non-empty |
| `ColSplit` | `COL_SPLIT(field, fmt, key)` | Split array to columns |
| `CurrentDate` | `CURDATE()` | Current date |
| `CurrentTime` | `CURTIME()` | Current time |
| `CurrentTimestamp` | `CURRENT_TIMESTAMP()` | Current unix timestamp |
| `DateAdd` | `DATE_ADD(date, interval)` | Add interval |
| `DateDiff` | `DATE_DIFF(date1, date2)` | Date difference |
| `DateFormat` | `DATE_FORMAT(date, fmt)` | Format date |
| `DateSub` | `DATE_SUB(date, interval)` | Subtract interval |
| `Day` | `DAY(date)` | Day of month |
| `FromUnixTime` | `FROM_UNIXTIME(ts, fmt)` | Unix timestamp to date |
| `Length` | `LENGTH(field)` | Length / count |
| `Month` | `MONTH(date)` | Month |
| `Now` | `NOW()` | Current datetime |
| `RandomBytes` | `RANDOM_BYTES(len)` | Random bytes |
| `SelectCase` | `CASE WHEN ... THEN ... END` | Case statement |
| `SelectIf` | `IF(cond, true, false)` | Conditional |
| `SelectIfNull` | `IFNULL(field, default)` | Null fallback |
| `SelectIsNull` | `ISNULL(field)` | Null check |
| `StrToDate` | `STR_TO_DATE(str, fmt)` | Parse string to date |
| `Uuid` | `UUID()` | Random UUID v4 |
| `Year` | `YEAR(date)` | Year |

### Hashing

| Class | SQL | Description |
|-------|-----|-------------|
| `Md5` | `MD5(field)` | MD5 hash |
| `Sha1` | `SHA1(field)` | SHA1 hash |

---

## Traits

**namespace:** `FQL\Traits`

### Select

`FQL\Traits\Select`

Provides field selection, function calls, aliasing, DISTINCT, and EXCLUDE.

_public_ **blockSelect():** `void` — blocks SELECT in DESCRIBE mode.

_public_ **isSelectEmpty():** `bool`

_public_ **select(**_string_ `...$fields`**):** `Interface\Query`

_public_ **selectAll():** `Interface\Query`

_public_ **distinct(**_bool_ `$distinct = true`**):** `Interface\Query`

_public_ **exclude(**_string_ `...$fields`**):** `Interface\Query`

_public_ **as(**_string_ `$alias`**):** `Interface\Query` — context-aware alias: after `select()`/function → aliases last field, after `from()` → aliases FROM source, after `join()` → aliases last join.

_public_ **custom(**_SingleFieldFunction|MultipleFieldsFunction|NoFieldFunction_ `$function`**):** `Interface\Query`

All function methods (`concat`, `lower`, `upper`, `round`, `count`, `sum`, `avg`, `min`, `max`, `groupConcat`, etc.) are defined here. See [Interface\Query](#query) for the complete list.

### Conditions (trait)

`FQL\Traits\Conditions`

Provides WHERE, HAVING, AND, OR, XOR conditions with grouping support.

_public_ **blockConditions():** `void`

_public_ **isConditionsEmpty():** `bool`

_public_ **where(**_string_ `$key`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Interface\Query`

_public_ **having(**_string_ `$key`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Interface\Query`

_public_ **and(**_string_ `$key`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Interface\Query`

_public_ **or(**_string_ `$key`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Interface\Query`

_public_ **xor(**_string_ `$key`**,** _Operator_ `$operator`**,** _array|float|int|string|Type_ `$value`**):** `Interface\Query`

_public_ **whereGroup():** `Interface\Query`

_public_ **havingGroup():** `Interface\Query`

_public_ **andGroup():** `Interface\Query`

_public_ **orGroup():** `Interface\Query`

_public_ **endGroup():** `Interface\Query`

_public_ **addWhereConditions(**_WhereConditionGroup_ `$group`**):** `Interface\Query`

_public_ **addHavingConditions(**_HavingConditionGroup_ `$group`**):** `Interface\Query`

### From

`FQL\Traits\From`

_public_ **from(**_string_ `$query`**):** `Query` — sets the data path within the file (e.g. `data.products`). Use `->as('alias')` after `from()` to alias the source for field access (e.g. `alias.field`, `alias.*`).

### Into

`FQL\Traits\Into`

_public_ **into(**_FileQuery_ `$fileQuery`**):** `static`

_public_ **hasInto():** `bool`

_public_ **getInto():** `?FileQuery`

### Joinable

`FQL\Traits\Joinable`

_public_ **blockJoinable():** `void`

_public_ **isJoinableEmpty():** `bool`

_public_ **join(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

_public_ **innerJoin(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

_public_ **leftJoin(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

_public_ **rightJoin(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

_public_ **fullJoin(**_Query_ `$query`**,** _string_ `$alias = ''`**):** `Query`

> Alias can be passed as a parameter or set fluently via `->as('alias')` after the join call. Alias is required — omitting it throws `JoinException` at query build time.

_public_ **on(**_string_ `$leftKey`**,** _Operator_ `$operator`**,** _string_ `$rightKey`**):** `Query`

### Groupable

`FQL\Traits\Groupable`

_public_ **blockGroupable():** `void`

_public_ **isGroupableEmpty():** `bool`

_public_ **groupBy(**_string_ `...$fields`**):** `self`

### Sortable

`FQL\Traits\Sortable`

_public_ **blockSortable():** `void`

_public_ **isSortableEmpty():** `bool`

_public_ **sortBy(**_string_ `$field`**,** _?Sort_ `$type = null`**):** `Query`

_public_ **orderBy(**_string_ `$field`**,** _?Sort_ `$type = null`**):** `Query`

_public_ **asc():** `Query`

_public_ **desc():** `Query`

_public_ **clearOrderings():** `Query`

### Limit

`FQL\Traits\Limit`

_public_ **blockLimitable():** `void`

_public_ **isLimitableEmpty():** `bool`

_public_ **limit(**_int_ `$limit`**,** _?int_ `$offset = null`**):** `Query`

_public_ **offset(**_int_ `$offset`**):** `Query`

_public_ **page(**_int_ `$page`**,** _int_ `$perPage = PER_PAGE_DEFAULT`**):** `Query`

### Unionable

`FQL\Traits\Unionable`

_public_ **blockUnionable():** `void`

_public_ **isUnionableEmpty():** `bool`

_public_ **union(**_Interface\Query_ `$query`**):** `Interface\Query`

_public_ **unionAll(**_Interface\Query_ `$query`**):** `Interface\Query`

### Explain

`FQL\Traits\Explain`

_public_ **blockExplain():** `void`

_public_ **isExplainEmpty():** `bool`

_public_ **explain():** `Interface\Query`

_public_ **explainAnalyze():** `Interface\Query`

### Describable

`FQL\Traits\Describable`

_public_ **isDescribeMode():** `bool`

_public_ **isDescribeEmpty():** `bool`

### Helpers

#### EnhancedNestedArrayAccessor

`FQL\Traits\Helpers\EnhancedNestedArrayAccessor`

_public_ **accessNestedValue(**_array_ `$data`**,** _string_ `$field`**,** _bool_ `$throwOnMissing = true`**):** `mixed`

_public_ **removeNestedValue(**_array_ `&$data`**,** _string_ `$field`**):** `void`

_public_ **isAssoc(**_array_ `$array`**):** `bool`

#### StringOperations

`FQL\Traits\Helpers\StringOperations`

_public_ **camelCaseToUpperSnakeCase(**_string_ `$input`**):** `string`

_public_ **isQuoted(**_string_ `$input`**):** `bool`

_public_ **isBacktick(**_string_ `$input`**):** `bool`

_public_ **translateToBacktickField(**_string_ `$field`**):** `string`

_public_ **removeQuotes(**_string_ `$input`**):** `string`

_public_ **extractPlainText(**_string_ `$input`**):** `string`

---

## Utils

**namespace:** `FQL\Utils`

### InMemoryHashmap (util)

`FQL\Utils\InMemoryHashmap` _implements_ `Interface\JoinHashmap`

Default in-memory hash map for JOIN operations.

_public_ **set(**_string|int_ `$key`**,** _array_ `$row`**):** `void`

_public_ **get(**_string|int_ `$key`**):** `array`

_public_ **getAll():** `array`

_public_ **has(**_string|int_ `$key`**):** `bool`

_public_ **getStructure():** `array`

_public_ **clear():** `void`

### FileQueryPath Validator

`FQL\Utils\FileQueryPathValidator`

_public static_ **validate(**_FileQuery_ `$fileQuery`**,** _string_ `$basePath`**,** _bool_ `$mustExist = true`**):** `FileQuery`

Validates file paths against base path. Prevents directory traversal.

---

## Next steps

- [Opening files](opening-files.md)
- [Fluent API](fluent-api.md)
- [File Query Language](file-query-language.md)
- [Fetching data](fetching-data.md)
- [Query Life Cycle](query-life-cycle.md)
- [FiQueLa CLI](fiquela-cli.md)
- [Query Inspection and Benchmarking](query-inspection-and-benchmarking.md)
- API Reference

or go back to [README.md](../README.md).
