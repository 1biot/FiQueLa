# Changelog

## [3.0.0]

This is a major rewrite of the FQL parser **and fluent API**. Two deeply related
shifts happened:

1.  The legacy monolithic `Sql\Sql` / `SqlLexer` are gone. The public
    `Query\Provider::fql()` entry point is unchanged. The new pipeline
    (Tokenizer → AST → Builder) opens the door to highlighters, formatters,
    an expression evaluator, and more.

2.  **Expression-First Fluent API.** Every fluent helper now accepts an SQL
    expression string and routes it through `Sql\Provider::parseExpression()`:
    `$query->sum('price + vat')`, `$query->groupBy('year(date)')`,
    `$query->where('lower(name)', Op::EQ, 'alice')`, `$query->select('if(x > 5, "big", "small")')`
    all work. The SQL builder and the fluent API converge on the **same code
    path** — there are no more `@internal` side doors (`addExpression`,
    `groupByExpression`, `orderByExpression`, `addAggregateExpression` are gone).
    `Functions\*` classes are now pure static utilities implementing the
    `ScalarFunction` / `AggregateFunction` contracts in `Functions\Core`; the
    global `Functions\FunctionRegistry` drives dispatch with auto-discovery
    from a shipped `functions.neon`, a runtime PHP cache, and user-facing
    `register()` / `unregister()` / `override()` / `loadConfig()` API.

### Added
- **`FQL\Sql\Token` namespace** — typed tokenizer foundation.
  - `TokenType` enum classifies every lexical element (keywords, identifiers, function
    names, literals, operators, file queries, structural tokens, whitespace/comment trivia).
  - `Token` and `Position` readonly value objects carry normalized values, raw lexemes,
    source positions (offset/line/column), and optional metadata (e.g. parsed `FileQuery`
    for `FILE_QUERY` tokens).
  - `TokenStream` cursor with `peek`/`consume`/`consumeIf`/`expect`/`mark`/`rewindTo`.
    Skips trivia by default; opt-in `includeTrivia` mode preserves whitespace and
    comments verbatim for highlighters and formatters.
  - `Tokenizer` — single-pass character-scanning lexer with line/column tracking. Emits
    `FILE_QUERY` as a single token after `FROM`/`INTO`/`DESCRIBE`/`JOIN`, supports
    dotted identifiers including `@` XML-attribute and kebab-case names, negative
    numeric literals in expression contexts, and reports unterminated strings/comments
    with exact position info.
- **`FQL\Sql\Ast` namespace** — abstract syntax tree nodes (immutable readonly VOs).
  - Statement / clause nodes: `SelectStatementNode`, `FromClauseNode`, `JoinClauseNode`,
    `WhereClauseNode`, `HavingClauseNode`, `GroupByClauseNode`, `OrderByClauseNode`,
    `OrderByItemNode`, `LimitClauseNode`, `UnionClauseNode`, `IntoClauseNode`,
    `SelectFieldNode`.
  - Expression nodes: `ColumnReferenceNode`, `LiteralNode`, `StarNode`, `FunctionCallNode`,
    `CastExpressionNode`, `MatchAgainstNode`, `CaseExpressionNode`, `WhenBranchNode`,
    `ConditionExpressionNode`, `ConditionGroupNode`, `SubQueryNode`, `FileQueryNode`,
    and new `BinaryOpNode` for arithmetic.
  - `JoinType`, `ExplainMode`, `BinaryOperator` enums.
- **`FQL\Sql\Parser` namespace** — recursive-descent parser split into one class per
  clause (`SelectClauseParser`, `FromClauseParser`, `JoinClauseParser`, `WhereClauseParser`,
  `HavingClauseParser`, `GroupByClauseParser`, `OrderByClauseParser`, `LimitOffsetParser`,
  `UnionParser`, `IntoParser`). `ExpressionParser` handles literals, function calls,
  `CAST`/`MATCH AGAINST`/`CASE`, **and infix arithmetic operators (`+`, `-`, `*`, `/`,
  `%`) via a Pratt-style precedence parser**. `ConditionParser` + `ConditionGroupParser`
  cover WHERE/HAVING with full operator coverage including nested groups. `ParseException`
  carries the offending token and expected token types for line/column-aware diagnostics.
- **`FQL\Sql\Builder` namespace** — translates the AST into an `Interface\Query`.
  - `QueryBuildingVisitor` walks the AST and invokes fluent `Query` methods.
  - `ExpressionCompiler` serialises AST nodes back to FQL strings for APIs that expect
    strings (`whenCase`, `elseCase`, `IF` condition).
  - `FileQueryResolver` wraps `FileQueryPathValidator` with the compiler's `basePath`.
  - **`ClauseRewriter`** auto-promotes complex expressions (function calls, binary
    arithmetic, CAST, CASE, MATCH) in `GROUP BY` / `ORDER BY` into computed SELECT
    fields referenced by alias. Auto-aliases (`__fql_auto_*`) are excluded from the
    result set, so `SELECT id FROM x ORDER BY LENGTH(name)` works out of the box.
- **`FQL\Sql\Function` namespace** — function dispatch registry.
  - `FunctionHandler` interface + `FunctionRegistry` with `register()` / `get()` /
    `has()` / `apply()` and a `default()` factory that pre-registers every built-in.
  - `GenericFunctionHandler` provides data-driven 1:1 mapping from FQL function name
    to the fluent `Interface\Query` API (with arity and DISTINCT constraints).
  - `ArgCoercion` helper converts AST argument nodes into the scalar/string forms
    expected by the Query API.
  - Category factories: `AggregateHandlers`, `HashingHandlers`, `MathHandlers`,
    `StringHandlers`, `UtilsHandlers`, `DateTimeHandlers`, `ConditionalHandlers`
    (plus the standalone `IfHandler` for the condition-argument IF function).
  - Registering a user-defined function is now a one-liner via
    `FunctionRegistry::register(new GenericFunctionHandler([...], fn(...) => ...))`.
- **`FQL\Sql\Highlighter` namespace** — syntax highlighting.
  - `Highlighter` + `Theme` interfaces, `ThemedHighlighter` base class.
  - `BashHighlighter` + `BashTheme` emit ANSI colour codes for terminal output.
  - `HtmlHighlighter` + `HtmlTheme` emit `<span class="fql-…">` with `htmlspecialchars`-
    escaped content. Companion stylesheet in `examples/highlighter.css`.
  - `HighlighterKind` enum (`BASH`, `HTML`).
  - `examples/highlight.php` CLI: `php examples/highlight.php [--html|--bash] "<SQL>"`.
- **`FQL\Sql\Formatter` namespace** — AST-driven pretty-printer.
  - `SqlFormatter` with `FormatterOptions` (indent, `uppercaseKeywords`, `fieldsPerLine`,
    `newline`). Emits one clause per line; multi-field SELECTs wrap onto separate lines.
- **`FQL\Sql\Provider`** — one-stop facade:
  - `compile($sql, $basePath)` → `Compiler` (tokens + AST + Query + `applyTo($existing)`).
  - `tokenize($sql, $includeTrivia)` → `TokenStream`.
  - `highlight($sql, $kind)` → ANSI or HTML string.
  - `format($sql, $options)` → pretty-printed SQL.
- **`FQL\Sql\Support` helpers** — used by internal consumers migrated off the legacy
  lexer:
  - `FieldListSplitter::split(...)` / `splitAlias(...)` — quote/paren/bracket-aware
    comma-split and `<expr> AS <alias>` separation.
  - `ConditionStringParser::populate(string, BaseConditionGroup)` — parses an FQL
    condition fragment into a populated runtime condition group.
- **`FQL\Sql\Runtime` namespace** — runtime expression evaluator that powers nested
  functions, arithmetic expressions, and expression-driven WHERE / HAVING / GROUP BY /
  ORDER BY clauses. Replaces the 3.0.0-beta `ClauseRewriter` auto-promote workaround.
  - `ExpressionEvaluator::evaluate(ExpressionNode, array $item, array $resultItem = []): mixed`
    walks the AST and resolves every node against a row: column references via
    `accessNestedValue`, function calls recursively via `FunctionInvoker`, binary
    arithmetic with SQL-style null propagation and zero-division handling, CAST, CASE,
    MATCH/AGAINST, and boolean conditions.
  - `FunctionInvoker` — thin dispatcher on top of `Functions\FunctionRegistry`.
    Resolves a function name to the registered class and calls its static
    `execute(...$args)` with already-evaluated AST arguments. Unknown names
    raise `Exception\UnknownFunctionException` at invocation.
- **`FQL\Conditions\ExpressionCondition`** — condition whose operands are AST
  `ExpressionNode`s rather than field-name strings. Built by the visitor for every
  WHERE/HAVING condition; delegates evaluation to `ExpressionEvaluator`, so
  `WHERE LOWER(name) = 'alice'` or `HAVING SUM(price * qty) > 1000` work without an
  explicit SELECT alias.
- **`FQL\Functions\FunctionRegistry`** — global static registry for every
  FQL function. Bootstraps from `src/Functions/functions.neon` (committed),
  caches the resolved `name → class` map to `fiquela-functions.compiled.php`
  in a writable location (`setCacheDir()` > library dir > system temp),
  invalidates on neon source mtime. Public lifecycle API:
  - `register(class-string)` — add a user-defined function. Strict on
    duplicate names; use `override()` to replace a built-in.
  - `unregister(string $name)` — drop a built-in or user function.
  - `loadConfig(string $neonPath)` — merge a custom neon source.
  - `getScalar` / `getAggregate` / `has` / `isAggregate` / `all` /
    `reset` — query + test helpers.
- **`Functions\Core\ScalarFunction`** and **`Functions\Core\AggregateFunction`**
  interfaces that every built-in function implements. Contracts:
  - Scalar: `name(): string` + a class-chosen `execute(...$args)` signature.
  - Aggregate: `name()` + `initial(array $options = [])` +
    `accumulate(mixed $acc, mixed $value)` + `finalize(mixed $acc)`. The
    `$options` bag carries `distinct` / `separator` per aggregate.
- **`Exception\UnknownFunctionException`** — raised at runtime when the
  registry does not know the invoked name. Subclass of
  `UnexpectedValueException`.
- **`Exception\FunctionRegistrationException`** — raised by the registry for
  duplicate / missing / malformed registration requests.
- **`Sql\Provider::parseExpression(string): ExpressionNode`** and
  **`parseCondition(string): ConditionGroupNode`** — public fragment parsers
  used by every fluent helper (`select('round(price * 1.21, 2)')`,
  `where('lower(name)', Op::EQ, 'alice')`, `case()->whenCase('x > 5', '"big"')`).
- **`Sql\Token\Position::synthetic()`** — factory returning a `Position(0, 0, 0)`
  used by fluent-API code paths that wrap plain field names into synthetic AST
  nodes.
- **`Functions\Utils\CaseBuilder`** — internal buffer that backs the
  `case()->whenCase()->elseCase()->endCase()` fluent builder; produces a
  `CaseExpressionNode` for the evaluator.

### Changed
- **`Query\Provider::fql()` runs on the new Token → AST → Query pipeline.** Public
  behaviour is preserved; parse errors are `FQL\Sql\Parser\ParseException` (a subclass
  of `FQL\Exception\UnexpectedValueException`) carrying the offending token and
  line/column info.
- **Infix arithmetic works anywhere an expression is accepted** — `SELECT price * 0.9`,
  `SUM(a + b)`, `ORDER BY price - 50`, `GROUP BY YEAR(date) % 10`. Parser uses standard
  C-family precedence (`*`/`/`/`%` > `+`/`-`) and left-associativity.
- **Nested function calls work in every clause** — `SELECT`, `WHERE`, `HAVING`,
  `GROUP BY`, and `ORDER BY` all resolve complex expressions (`UPPER(LOWER(name))`,
  `ROUND(5 * price, 2)`, `LENGTH(CONCAT(first, " ", last))`) row-by-row via the
  runtime evaluator. No auxiliary `__fql_auto_*` SELECT fields, no manual alias
  chaining — `WHERE LOWER(name) = 'alice'` and `ORDER BY LENGTH(name) DESC` now work
  end-to-end.
- **Aggregates accept expressions as arguments** — `SUM(price * qty)`,
  `AVG(total - discount)`, `COUNT(DISTINCT LOWER(email))` are evaluated
  directly via the `AggregateFunction` interface in
  `Results\Stream::applyGrouping` (initial / accumulate / finalize).
  `COUNT(*)` stays on the Count fast path — the StarNode evaluates to `'*'`
  and Count increments unconditionally on non-null input.
- **Fluent helpers are now SQL-expression-aware.** Every scalar helper
  (`lower`, `upper`, `round`, `concat`, `concatWithSeparator`, `year`,
  `month`, `dateAdd`, `cast`, `strToDate`, `if`, `ifNull`, `isNull`, …)
  parses its field argument with `Sql\Provider::parseExpression()`. Aggregate
  helpers (`sum`, `avg`, `count`, `min`, `max`, `groupConcat`) do the same
  and populate the SELECT field's `aggregate` slot with a `{class, expression,
  options}` spec. `$query->sum('price + vat')`, `$query->lower('upper(field)')`,
  `$query->groupBy('year(date)')`, `$query->where('lower(name)', Op::EQ, 'alice')`
  all work end-to-end.
- **`Functions\*` classes are static-only utility containers.** Instance
  state, `__construct`, `__invoke` and `__toString` were removed; they
  implement `ScalarFunction` / `AggregateFunction` and only define
  `name()` + the matching static execution entry points. The legacy
  `applyValue()` / `applyValues()` helpers are renamed to a unified
  **`execute(...)`**.
- **`QueryBuildingVisitor` uses the public fluent API exclusively.**
  Every SELECT / GROUP BY / ORDER BY clause is stringified via
  `ExpressionCompiler` and fed back into `$query->select()` /
  `$query->groupBy()` / `$query->orderBy()`, which in turn parse through
  `Sql\Provider::parseExpression()`. No privileged `addExpression()` /
  `groupByExpression()` / `orderByExpression()` side doors remain. The
  ~50 µs/clause stringify/reparse hop is negligible vs stream setup.
- **Unified internal field representation.** `Traits\Groupable::$groupByFields`
  and `Traits\Sortable::$orderings` are `ExpressionNode[]`, and every
  `Traits\Select::$selectedFields[*]` entry carries `{originField, alias,
  expression: ExpressionNode|null, aggregate: {class, expression, options}|null}`.
  Fluent `groupBy('field')` / `orderBy('field')` / `select('field')` wrap
  plain names into a synthetic `ColumnReferenceNode` so the runtime
  (`Results\Stream::createGroupKey`, `applySorting`, `applySelect`) has a
  single evaluation path via `ExpressionEvaluator`.
- **WHERE / HAVING left-hand side parses as an expression.** `$query->where('lower(name)', Op::EQ, 'alice')`
  and `$query->having('sum(price)', Op::GT, 100)` produce
  `Conditions\ExpressionCondition`. The right-hand side continues to accept
  scalar literals / arrays / `Type` enum — SQL would require an explicit
  literal quote that the fluent API does not encode.
- **`Traits\Select::select()` now recognises `AS <alias>` inside the field spec**:
  `$query->select('name AS n')` behaves like `$query->select('name')->as('n')`. The
  legacy behaviour (which produced three separate fields `name`, `AS`, `n`) was a bug.
- Internal consumers migrated onto the new pipeline: `Traits\Select::select/exclude`,
  `Functions\Utils\SelectIf`, `Functions\Utils\SelectCase`, `Query\Debugger::inspectStreamSql`,
  `Stream\AbstractStream::fql`, all `tests/SQL/*` plus `tests/Query/UnionTest` and
  `tests/Functions/Utils/UuidTest`.

### Removed (BREAKING)
- **`FQL\Sql\Sql`** — replaced by `FQL\Sql\Provider::compile()` / `FQL\Sql\Compiler`.
  - `new Sql($sql)` → `Sql\Provider::compile($sql)`
  - `->parseWithQuery($query)` → `->applyTo($query)`
  - `->parse()` → `->toQuery()->execute()`
  - `->toQuery()` → unchanged
- **`FQL\Sql\SqlLexer`** — replaced by `FQL\Sql\Token\Tokenizer`. The iterator /
  `parseSingleCondition` / `parseConditionGroup` helpers moved to
  `FQL\Sql\Parser\{ConditionParser, ConditionGroupParser}` and
  `FQL\Sql\Support\ConditionStringParser`.
- **`FQL\Interface\Parser`** interface — no replacement; use the Compiler directly.
- **`FQL\Sql\Function` namespace** (`FunctionRegistry`, `FunctionHandler`,
  `GenericFunctionHandler`, `ArgCoercion`, and the whole `Handler\` subfolder) —
  entirely replaced by the runtime `FunctionInvoker`. The data-driven handler
  pattern is no longer required because the evaluator dispatches directly to the
  `applyValue()` helpers on the `Functions\*` classes.
- **`FQL\Sql\Builder\ClauseRewriter`** (3.0.0-beta auto-promote workaround) —
  replaced by the runtime evaluator, which handles complex expressions in GROUP BY /
  ORDER BY natively without introducing auxiliary SELECT columns.
- **`FQL\Sql\Runtime\ExpressionAggregate`** wrapper — replaced by the direct
  `Functions\Core\AggregateFunction` static contract (`initial` / `accumulate` /
  `finalize`). Query state keeps the aggregate as `{class, expression, options}`
  metadata consumed by `Results\Stream::applyGrouping`.
- **`FQL\Functions\Core` legacy abstract classes** — `BaseFunction`,
  `BaseFunctionByReference`, `SingleFieldFunction`,
  `SingleFieldFunctionByReference`, `MultipleFieldsFunction`, `NoFieldFunction`,
  the old abstract `AggregateFunction`, and `SingleFieldAggregateFunction` are
  all deleted. Functions are now pure static utilities implementing the new
  `ScalarFunction` / `AggregateFunction` interfaces in the same namespace.
- **`FQL\Interface` Invokable hierarchy** — `Invokable`, `InvokableAggregate`,
  `InvokableByReference`, `InvokableNoField`, `IncrementalAggregate` removed.
  The new function contracts live in `Functions\Core`.
- **`FQL\Functions\Utils\SelectCase`** instance builder — replaced by
  `Functions\Utils\CaseBuilder`, invoked by the new fluent `case()` chain
  which now parses WHEN / THEN / ELSE strings through the expression
  evaluator.
- **`FQL\Interface\Query` public methods**
  - `addExpression(ExpressionNode, ?alias)`
  - `addAggregateExpression(ExpressionAggregate, ?alias)`
  - `groupByExpression(ExpressionNode)`
  - `orderByExpression(ExpressionNode, ?Sort)`
  - `custom(...)` fluent escape hatch
    — all removed. The fluent helpers now accept SQL expression strings and
    route everything through the same parser that powers the SQL builder.
    User-defined functions are registered through `Functions\FunctionRegistry`
    at bootstrap instead of passing an instance into `custom()`.
- **`league/csv` dependency dropped** — CSV reader and writer now run on
  `openspout/openspout` (already a direct dependency for XLSX/ODS), so the
  library ships with one fewer vendor package. Reader switches to
  `OpenSpout\Reader\CSV\Reader` (`Options::FIELD_DELIMITER`, `::ENCODING`,
  `::SHOULD_PRESERVE_EMPTY_ROWS`) — encoding conversion is handled by
  OpenSpout's iconv-backed `EncodingHelper`. Writer switches to
  `OpenSpout\Writer\CSV\Writer` with the UTF-8 BOM opt-out (preserves
  byte-for-byte compatibility with the prior writer); non-UTF-8 output
  encodings (`encoding: "windows-1250"`) are produced by pre-converting each
  string cell through `OpenSpout\Common\Helper\EncodingHelper`
  (`attemptConversionFromUTF8()`) before it is handed to
  `Row::fromValues()` — OpenSpout's `fputcsv` then writes byte-level, which
  is ASCII-safe for CSV delimiter and enclosure in every supported encoding.
  All existing FileQuery CSV options (`encoding`, `delimiter`, `useHeader`,
  positional or named) work unchanged — no migration needed on user queries.
- **CSV reader hot-path speedup.** `CsvProvider::getStreamGenerator()` now
  short-circuits `Enum\Type::matchByString()` for cell values that clearly
  aren't typed literals (first character not a quote/sign/digit/decimal
  separator and length not 4 or 5 — the same heuristic already used by
  `SpreadsheetProvider::normalizeCellValue()`). Free-form text columns
  (names, descriptions, category paths) skip the regex + `in_array` probes
  inside `matchByString` entirely. Assoc-row assembly uses native
  `array_combine()` (with explicit `array_pad` / `array_slice` for
  malformed rows) instead of a per-row PHP `foreach`. ~20 % faster on a
  representative product CSV.

## [2.12.0]

### Added
- **FROM aliasing**: `FROM source AS alias` in FQL and `->from('source')->as('alias')` in fluent API. Aliased fields accessible via `alias.field` dot notation.
- **Fluent JOIN aliasing**: `->join($query)->as('alias')->on(...)` as alternative to passing alias as parameter. Backward-compatible — `->join($query, 'alias')` still works.
- **Aliased wildcard `alias.*`**: select all fields from an aliased source (FROM or JOIN) using `alias.*` in SELECT. Throws `SelectException` on ambiguous field conflicts.
- **Wildcard `*` support in `EnhancedNestedArrayAccessor`**: path traversal now supports `*` token to expand all keys of an associative array.
- **Context-aware `as()` method**: `as()` in `Query` now detects context — aliases SELECT field, FROM source, or JOIN depending on what was called before it.
- `LastClause` enum (`FQL\Enum\LastClause`) for internal context tracking.
- **Subquery JOIN support** in FQL parser: `LEFT JOIN (SELECT ... FROM ... WHERE ...) AS alias ON ...`. Parser recursively handles nested SELECT statements in JOIN clauses.
- `Query::isSimpleQuery()` method to detect queries without any clauses (SELECT * FROM source only).
- `Query::provideFileQuery(bool $withQuery = false)` parameter to include FROM path in the returned FileQuery.

### Changed (BREAKING)
- **Commas are now mandatory** between expressions in SELECT, GROUP BY, and ORDER BY clauses in FQL strings. `SELECT id, name, price` is valid; `SELECT id name price` throws `UnexpectedValueException`. Fluent API (`->select('id, name')`) is unaffected.

### Changed
- `as()` moved from `Select` trait to `Query` class as a unified context-aware method. Internally delegates to `asSelect()`, `asFrom()`, or `asJoin()`.
- JOIN methods (`join`, `innerJoin`, `leftJoin`, `rightJoin`, `fullJoin`) now accept alias as optional parameter (`string $alias = ''`). Alias is still required but can be set via `->as()` fluently.
- JOIN `__toString()` renders simple joins as direct source references instead of subqueries.
- `EnhancedNestedArrayAccessor::parsePath()` token type extended with `wildcard` flag.
- JOIN `ON` conditions now resolve dot-notation keys via `accessNestedValue()`, supporting aliased field paths (e.g. `ON u.id = c.id`).
- `SqlLexer` tokenizer now respects parenthesis depth — control keywords inside `(...)` are not treated as block delimiters.
- `SqlLexer::defaultTokenize()` now emits commas as separate tokens instead of stripping them.

### Fixed
- FQL parser: `LIMIT` before `UNION` no longer consumes the `UNION` token as offset.
- FQL parser: `FROM ... AS alias` tokenization and parsing support in `SqlLexer`.

## [2.11.0]

### Added
- HTTP access log stream provider (`log` format) for querying Apache/Nginx access logs
- Predefined log format profiles: `nginx_combined` (default), `nginx_main`, `apache_combined`, `apache_common`
- Custom log format patterns via Apache log_format tokens (`%h`, `%r`, `%>s`, `%{Referer}i`, etc.)
- Automatic value normalization for access logs (status→int, time→Y-m-d H:i:s, %D μs→ms, %r→method/path/protocol split)
- Graceful error handling: malformed lines yield error rows (`_error` field) instead of throwing
- FQL syntax: `FROM log(file).*`, `FROM log(file, "profile").*`, `FROM log(file, format: "profile").*`

## [2.10.2]

### Added
- Complete [API Reference](docs/api-reference.md) documentation covering all enums, exceptions, interfaces, query classes, results, stream providers, writers, SQL parser, conditions, functions, traits, and utilities.

### Changed (BREAKING)
- `ResultsProvider::into()` now returns `?FileQuery` instead of `?string` — the returned FileQuery contains the effective query with defaults applied.
- `Writer` interface now requires `getFileQuery(): FileQuery` method.

### Changes
- Added bold style to ODS and XLSX headers

### Fixed
- Writers now apply default query fallback via `FileQuery::withQuery()` so the returned FileQuery is ready for reading back the written file:
  - CSV, NDJSON, JSON (without query): defaults to `*`
  - XML (without query): defaults to `rows.row` (matching the default root/row elements)
  - XLSX/ODS: preserves sheet name if set
- JSON writer no longer wraps data in `{"*": [...]}` when query is explicitly `*` — writes flat array instead.
- XLSX/ODS writer treats `*` query as default (first sheet from A1) instead of parsing `*` as sheet name.

## [2.10.1]

### Fixed
- Fixed issue with streaming `DESCRIBE` results when data source use `\Generator`

### Changed
- Changed interface `AggregableResult` to `Aggregable` only

### Added
- Added array path to `DESCRIBE` result for correctly evaluating nested fields (e.g. `Items`.`Item`.`Prod. cena`.`s dph` instead of `Items.Item.Prod. cena.s dph`)

## [2.10.0]

### Added
- `DESCRIBE` clause for inspecting data source schema — returns column names, types, statistics (confidence, completeness, uniqueness, enum detection).
- `DescribeResult` class extending `ResultsProvider` with single-pass column analysis.
- `Describable` trait with `isDescribeMode()`, `isDescribeEmpty()`, and `enableDescribe()`.
- `AggregableResult` interface extracted from `Results` interface for `sum()`, `avg()`, `min()`, `max()`.
- Blocking mechanism in traits (`Select`, `Conditions`, `Groupable`, `Sortable`, `Limit`, `Joinable`, `Unionable`, `Explain`) — `DESCRIBE` is mutually exclusive with `SELECT`, `WHERE`, `GROUP BY`, `ORDER BY`, `LIMIT`, `JOIN`, `UNION`, `EXPLAIN`.
- FQL SQL support: `DESCRIBE json(file.json).data.products`.
- Debugger highlighting for `DESCRIBE` keyword.

### Fixed
- `ORDER BY` now supports nested fields via dot notation (e.g. `orderBy('brand.code')`) — previously only flat keys were accessible.

### Changed (BREAKING)
- `sum()`, `avg()`, `min()`, `max()` removed from `Interface\Results` — moved to new `Interface\AggregableResult`.
- `Stream` and `InMemory` now implement `AggregableResult` interface explicitly.

## [2.9.0]

### Added
- Added `INTO` clause parsing and query metadata support (`into()`, `hasInto()`, `getInto()`) for FQL queries.
- Added export writers and factory for `csv`, `ndjson`, `json`, `xml`, `xlsx`, `ods`.
- Added `ResultsProvider::into(FileQuery|string $fileQuery)` for exporting query results.
- Added `FQL\Interface\Writer` and `FileAlreadyExistsException`.

### Changed
- Path validation for file queries with base path now supports non-existing target files while preserving directory traversal protection.
- SQL lexer now recognizes `INTO` as a control keyword.

### Notes
- Existing target files are never overwritten automatically (throws `FileAlreadyExistsException`).
- Output directories are created recursively by `ResultsProvider::into()`.

## [2.8.0]

### Changed (BREAKING)
- New FileQuery syntax `format(file, params).query` — square brackets removed
- Old syntax `[format](file, encoding, delimiter).query` is no longer supported
- Bare file references `(file.xml).query` without format prefix are no longer supported — format is now required
- `FileQuery::getRegexp()` no longer accepts `$defaultPosition` parameter
- `FileQuery` properties `encoding` and `delimiter` replaced by `params` array and `getParam()` method
- `FileQuery::withExtension()` replaced by `withFormat(?string $format)`

### Added
- General parameter system for FileQuery — positional `"value"` or named `key: "value"` syntax
- `Format::getDefaultParams()`, `Format::normalizeParams()`, `Format::validateParams()` methods
- `FileQuery::getParam()`, `FileQuery::withParam()`, `FileQuery::withFormat()` methods
- Parameter validation: encoding checked via `iconv`, CSV delimiter must be single character

### Deprecated
- `FileQuery::withEncoding()` — use `withParam('encoding', ...)` instead
- `FileQuery::withDelimiter()` — use `withParam('delimiter', ...)` instead

## [2.7.2]

### Added
- `mem_peak_kb` column to `EXPLAIN ANALYZE` output — records `memory_get_peak_usage()` at the end of each phase
- Union sub-phase instrumentation in `EXPLAIN ANALYZE` — each union branch now reports its own phases (`union_1_stream`, `union_1_where`, etc.) followed by a summary row (`union_1`)
- `setCollector()` method on `Stream` for passing `ExplainCollector` with prefix to union subqueries
- `recordMemPeak()` and `prefixPhase()` helper methods

### Changed
- Union phases renamed from `union` to `union_{index}` (e.g. `union_1`, `union_2`) in both `EXPLAIN` and `EXPLAIN ANALYZE`
- For non-ANALYZE `explain()`, `mem_peak_kb` is always `null`

## [2.7.1]

### Updated
- Refactored `EXPLAIN` and `EXPLAIN ANALYZE` methods

### Fixed
- added support for `UNION` and `UNION ALL`

## [2.7.0]

### Added
- support for `UNION` and `UNION ALL`
- Added `UUID` function

## [2.6.0]

### Changed

- Refactored `\FQL\Conditions\BaseConditionGroup` and make it abstract and implements concrete condition
groups `WhereConditionGroup` and `HavingConditionGroup` and `IfStatementConditionGroup` and `CaseStatementConditionGroup`

### Added
- `IF` and `CASE-WHEN` supporting multiple conditions as `WHERE` or `HAVING` do.
- New operator `REGEXP` and `NOT REGEXP`

## [2.5.3]

### Changed
- changed library for parsing `XLSX`

### Added
- Added support for `ODS` files

### Removed
- dropped support for old `XLS` files

## [2.5.2]

### Added
- information about method `ARRAY_SEARCH` and `ARRAY_FILTER` into the docs

### Fixed
- dependency of `symfony/yaml` for php 8.2 compatibility

## [2.5.1]

### Added
- new method `ARRAY_SEARCH`
- `JoinHashmapInterface` with `InMemoryHashmap`

### Fixed
- method `REPLACE` now could replace values for array accessor
- `IN` operator knows list of values by array accessor - not done yet
- removed premature `unset($hashmap[$leftKey])` causing LEFT JOIN to miss duplicate left-side keys
- release right-side iterator from memory after hashmap is built (`unset($rightData)`)

### Removed
- FiQueLa CLi has been moved to own repository at https://github.com/1biot/fiquela-cli

## [2.5.0]

### Added
- Added `EXPLAIN` and `EXPLAIN ANALYZE` for FQL and Fluent API, returning flat InMemory results with plan details and metrics.

## [2.4.2]
- Increased code coverage to 80%

## [2.4.1]

### Fixed
- Fixed issue with parsing values for `IN` and `NOT IN` operators when using FQL syntax
- Fixed processing parameters for `ADD`, `SUB`, `MULTIPLY`, `DIVIDE` functions

## [2.4.0]

### Added
- Added code coverage report
- [FiQueLa CLI](https://github.com/1biot/fiquela-cli) is part of the main repository now
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
