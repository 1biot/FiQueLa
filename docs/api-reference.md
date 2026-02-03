# API Reference

WORK IN PROGRESS ...

**table of contents**:
- [Enums](#enums)
  - [Format](#format)
  - [Fulltext](#fulltext)
  - [Join](#join)
  - [LogicalOperator](#logicaloperator)
  - [Operator](#operator)
  - [Sort](#sort)
  - [Type](#type)
- Exceptions
- [Interfaces](#interfaces)
  - Invokable
  - InvokableAggregate
  - InvokableNoField
  - Parser
  - Query
  - Result
  - Stream
- Traits

## Enums

**namespace:** `FQL\Enum`

### Format

`FQL\Enum\Format`

- **CSV**: `csv`
- **JSON**: `json`
- **JSON_STREAM**: `jsonFile` 
- **NEON**: `neon`
- **XML**: `xml`
- **YAML**: `yaml`

_public_ **getFormatProviderClass():**`class-string`

_public_ **openFile(**_string_`$path`**):**`class-string`

_public_ **fromString(**_string_`$data`**):**`FQL\Interface\Stream`

_public_ _static_ **fromExtension(**_string_`$extension`**):**`self`

### Fulltext

`FQL\Enum\Fulltext`

- **NATURAL**: `NATURAL`
- **BOOLEAN**: `BOOLEAN`

_public_ **calculate(**_string_`$query`**,** _array_`$terms`**):**`float`

### Join

`FQL\Enum\Join`

- **INNER**: `INNER JOIN`
- **LEFT**: `LEFT JOIN`
- **RIGHT**: `RIGHT JOIN`

### LogicalOperator

`FQL\Enum\LogicalOperator`

- **AND**: `AND`
- **OR**: `OR`
- **XOR**: `XOR`

_public_ **evaluate(**_?bool_`$left`**,** _bool_`$right`**):**`bool`

_public_ **render(**_bool_`$spaces = false` **):**`string`

### Operator

`FQL\Enum\Operator`

- **EQUAL**: `=`
- **EQUAL_STRICT**: `==`
- **NOT_EQUAL**: `!=`
- **NOT_EQUAL_STRICT**: `!==`
- **GREATER_THAN**: `>`
- **GREATER_THAN_OR_EQUAL**: `>=`
- **LESS_THAN**: `<`
- **LESS_THAN_OR_EQUAL**: `<=`
- **IN**: `IN`
- **NOT_IN**: `NOT IN`
- **LIKE**: `LIKE`
- **NOT_LIKE**: `NOT LIKE`
- **IS**: `IS`
- **NOT_IS**: `IS NOT`

_public_ **render(**_bool_`$spaces = false` **):**`string`

### Sort

`FQL\Enum\Sort`

- **ASC**: `ASC`
- **DESC**: `DESC`

### Type

`FQL\Enum\Type`

- **BOOLEAN**: `boolean`
- **TRUE**: `TRUE`
- **FALSE**: `FALSE`
- **NUMBER**: `number`
- **INTEGER**: `integer`
- **FLOAT**: `double`
- **STRING**: `string`
- **NULL**: `NULL`
- **ARRAY**: `array`
- **OBJECT**: `object`
- **RESOURCE**: `resource`
- **RESOURCE_CLOSED**: `resource (closed)`
- **UNKNOWN**: `unknown type`

## Exceptions

**namespace:** `FQL\Exception`

## Interfaces

**namespace:** `FQL\Interface`

### Invokable

`FQL\Interfae\Invokable`

### InvokableAggregate

`FQL\Interfae\InvokableAggregate`

### InvokableNoField

`FQL\Interface\InvokableNoField`

### Parser

`FQL\Interface\Parser`

### Query

`FQL\Interface\Query`



### Result

`FQL\Interface\Result`

### Stream

`FQL\Interface\Stream`

## Traits

**namespace:** `FQL\Traits`

...

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


