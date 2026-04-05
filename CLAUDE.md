# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

FiQueLa (File Query Language) — PHP 8.2+ library for SQL-like querying of structured files (CSV, JSON, XML, YAML, NEON, XLSX, ODS) without a database.

Package: `1biot/fiquela`.

## Commands

```bash
composer install              # Install dependencies
composer test                 # Full suite: PHPCS + PHPStan + PHPUnit with coverage
composer test:phpunit          # PHPUnit only
composer test:phpstan          # PHPStan only (level 8)
composer test:phpcs:summary    # PHPCS only (PSR-12)
composer test:phpunit:coverage # PHPUnit with coverage text

# Single test file
vendor/bin/phpunit tests/Functions/Math/AddTest.php

# Single test method
vendor/bin/phpunit --filter testAdd tests/Functions/Math/AddTest.php

# Run examples
composer examples              # All examples
composer example:sql           # Single example
```

## Architecture

### Query Pipeline

```
Stream\Provider::fromFile() → Stream (data source)
    → Query\Query (orchestrator, fluent API)
        → Results\Stream (generator-based, memory-efficient)
        → Results\InMemory (array-based, required for JOIN/ORDER BY)
```

Alternative entry: `Query\Provider::fql($sql)` parses FQL string via `Sql\Sql` parser into a `Query` object.

### Key Components

- **Stream providers** (`src/Stream/`): Each file format has a class extending `AbstractStream`. `Stream\Provider` factory auto-detects format from file extension.
- **Query** (`src/Query/Query.php`): Main orchestrator composed of 12 traits (Select, From, Conditions, Groupable, Joinable, Sortable, Limit, Unionable, Into, Explain, Describable). Implements `Interface\Query`.
- **SQL parser** (`src/Sql/Sql.php` + `SqlLexer.php`): Tokenizes and parses FQL syntax into Query objects. Function mappings live in `applyFunctionToQuery()`.
- **Functions** (`src/Functions/`): Each function is a class with `__invoke($item, $resultItem)`. Base classes in `Functions/Core/` (BaseFunction, AggregateFunction, SingleFieldFunction, etc.). Categories: Aggregate, Math, String, Utils, Hashing.
- **Results** (`src/Results/`): `Stream` for memory-efficient iteration, `InMemory` when full dataset needed. `DescribeResult` for schema inspection.
- **Conditions** (`src/Conditions/`): WHERE/HAVING condition groups with `SimpleCondition` and `GroupCondition`.

### Adding a New Function

1. Create class in `src/Functions/<Category>/` extending appropriate base from `Functions/Core/`
2. Wire into `src/Traits/Select.php` via `addFieldFunction()`
3. Add parser mapping in `src/Sql/Sql.php` → `applyFunctionToQuery()`
4. Update `src/Interface/Query.php` with method signature
5. Add tests in `tests/Functions/<Category>/`
6. Update docs (`docs/fluent-api.md`, `docs/file-query-language.md`) and `CHANGELOG.md`

### Design Decisions

- **No `declare(strict_types=1)`** — intentional project-wide convention, keep consistent.
- **Stream vs InMemory**: `execute()` auto-selects Stream unless JOIN or ORDER BY requires InMemory.
- **String literals**: Quoted values (`"..."` or `'...'`) are literals; unquoted are field references. Use `Type::matchByString()` for parsing.
- **Trait composition over inheritance**: Query capabilities split across focused traits sharing state via class properties.

## Code Style and Conventions

- **PSR-12** enforced via PHP_CodeSniffer (`phpcs.xml`). 4-space indentation, no tabs.
- **PSR-4** autoloading: `FQL\` → `src/`. Tests use namespaces like `Functions\...` under `tests/`.
- Use typed properties and return types everywhere. Union types where needed (e.g., `int|float|string|array|Type`).
- No `declare(strict_types=1)` — keep consistent across the codebase.
- Classes: `PascalCase`. Methods: `camelCase`. Enum values: `UPPER_SNAKE_CASE`. SQL keywords: use `Interface\Query` constants.
- Throw project-specific exceptions from `FQL\Exception\` (`SelectException`, `AliasException`, `QueryLogicException`). For invalid function inputs use `UnexpectedValueException`.
- For functions operating on fields, accept `string` references and rely on `Type::matchByString()` to parse literal values.

## Testing

- PHPUnit `TestCase` with `assertEquals` for numeric comparisons.
- Tests organized by type in `tests/` (Functions/, Traits/, Stream/, SQL/, Query/, etc.).
- Use `expectException()` for error-path tests.
- Function tests pattern: construct function with field names, invoke via `__invoke($item, $resultItem)`, assert result.
