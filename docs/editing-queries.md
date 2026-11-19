# Editing Queries

FiQueLa can change a parsed FQL query and write it back while keeping the user's text:
whitespace, line breaks, comments, backticks, quotes and keyword case stay exactly as
written everywhere except in the parts that changed. Editors and GUIs (sort by a column
header, add a filter, change the source, hide a column) use this to edit a query the
user also writes by hand.

**table of contents**:

* [Quick start](#quick-start)
* [How the printer decides](#how-the-printer-decides)
* [Building edited trees](#building-edited-trees)
* [Recipes](#recipes)
* [Source spans](#source-spans)
* [Fresh printing](#fresh-printing)
* [Writing file queries](#writing-file-queries)
* [Errors](#errors)
* [Guarantees and limitations](#guarantees-and-limitations)

## Quick start

```php
use FQL\Enum\Sort;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Node\OrderByClauseNode;
use FQL\Sql\Ast\Node\OrderByItemNode;
use FQL\Sql\Printer\SourcePrinter;
use FQL\Sql\Token\Position;

$sql = <<<FQL
select name, price -- the price
from csv(./products.csv, delimiter: ";")
where price > 100
order by name
FQL;

$printer = new SourcePrinter($sql);           // parses $sql
$ast = $printer->original();                  // FQL\Sql\Ast\Node\SelectStatementNode

$p = Position::synthetic();
$edited = $ast->withOrderBy(new OrderByClauseNode(
    [new OrderByItemNode(new ColumnReferenceNode('price', $p), Sort::DESC, $p)],
    $p
));

echo $printer->print($edited);
// select name, price -- the price
// from csv(./products.csv, delimiter: ";")
// where price > 100
// order by price desc
```

Only `name` became `price desc`; the comment, the lowercase keywords and the named
CSV parameter are untouched, and the new `desc` follows the query's lowercase style.

`print($printer->original())` returns the original SQL byte for byte.

## How the printer decides

AST nodes are immutable. An edit rebuilds only the path from the root to the changed
node, so every untouched subtree is still *the same object* as in `original()`.
`SourcePrinter` relies on that identity:

1. **An original node is written as its original text** (comments inside it included),
   wherever it appears in the edited tree — moved fields keep their spelling.
2. **A changed node is written against the original node in the same place**: its
   keywords, punctuation and the gaps between its parts are copied from the original,
   only the changed parts are written fresh. Changing `price>100` to `price>200` gives
   `price>200`, not `price > 200`; a changed `LEFT OUTER JOIN` source keeps `LEFT OUTER JOIN`.
3. **Lists** (SELECT fields, conditions, function arguments, `IN` values, ORDER BY / GROUP BY
   items, CTEs, joins, clauses):
   - the separator between two items that were neighbours stays verbatim;
   - a removed item takes its separator along (`, field`, `AND condition`, the line of a
     removed clause);
   - an inserted item copies the layout of the nearest existing separator, without its
     comments — a new condition in a multi-line `WHERE` goes on its own line with the same
     indentation, a new clause goes on its own line when the statement is multi-line;
   - comments belong to items: a comment after an item on its line (`price, -- money`)
     moves and disappears with that item, comments on the lines before an item stay
     in front of it.
4. **New clauses** go to their canonical place (`WITH`, `SELECT`, `FROM`, `JOIN`, `WHERE`,
   `GROUP BY`, `HAVING`, `ORDER BY`, `LIMIT`, `INTO`, `UNION`) relative to the clauses the
   query already has; existing clauses keep their order.
5. **New keywords** (and function names, `TRUE` / `FALSE` / `NULL`) follow the keyword case
   of the original query (the majority of its keywords, see `keywordCase()`).
6. Anything with no original counterpart is written fresh by the rules of
   [`FqlPrinter`](#fresh-printing).

Text around the root statement (a leading comment, a trailing newline) is kept.

## Building edited trees

Every node keeps its public constructor; most nodes also have `with…()` helpers that
return a copy with one property replaced. Copies have no [span](#source-spans) — they no
longer match the source — but share all other children with the original.

| Node | Helpers |
|------|---------|
| `SelectStatementNode` | `withFrom()`, `withFields()`, `withDistinct()`, `withJoins()`, `withWhere()`, `withGroupBy()`, `withHaving()`, `withOrderBy()`, `withLimit()`, `withUnions()`, `withInto()`, `withExplain()`, `withCommonTables()` |
| `SelectFieldNode` | `withExpression()`, `withAlias()`, `withExcluded()` |
| `FromClauseNode` | `withSource()`, `withAlias()` |
| `JoinClauseNode` | `withType()`, `withSource()`, `withAlias()`, `withOn()` |
| `WhereClauseNode`, `HavingClauseNode` | `withConditions()` |
| `GroupByClauseNode` | `withFields()` |
| `OrderByClauseNode` | `withItems()` |
| `OrderByItemNode` | `withExpression()`, `withDirection()` |
| `LimitClauseNode` | `withLimit()`, `withOffset()` |
| `ConditionGroupNode` | `withEntries()`, `withAppended(LogicalOperator, condition)`, `withoutEntry(int)` |
| `ConditionExpressionNode` | `withLeft()`, `withOperator()`, `withRight()` |
| `FunctionCallNode` | `withArguments()` |
| `FileQueryNode` | `withFileQuery(FileQuery, string $raw = '')` |

Setting a clause to `null` (`withWhere(null)`) removes it. Nodes you create yourself use
`Position::synthetic()`; their position is not used for printing.

Nodes parsed from *another* SQL string (e.g. a condition parsed from a snippet) are not
part of `original()`, so they are printed fresh, not as text of the snippet.

## Recipes

All examples use `$printer = new SourcePrinter($sql); $ast = $printer->original();`
and `$p = Position::synthetic()`.

**Toggle a sort direction**

```php
$item = $ast->orderBy->items[0];
$printer->print($ast->withOrderBy($ast->orderBy->withItems([$item->withDirection(Sort::DESC)])));
// ORDER BY name  →  ORDER BY name DESC
```

`ASC` is written only when the original item spelled out a direction (`name DESC` →
`name ASC`, `name` stays `name`).

**Add a condition**

```php
$condition = new ConditionExpressionNode(
    new ColumnReferenceNode('stock', $p), Operator::GREATER_THAN, new LiteralNode(0, Type::INTEGER, '', $p), $p
);
$where = $ast->where === null
    ? new WhereClauseNode(new ConditionGroupNode([['logical' => LogicalOperator::AND, 'condition' => $condition]], $p), $p)
    : $ast->where->withConditions($ast->where->conditions->withAppended(LogicalOperator::AND, $condition));
$printer->print($ast->withWhere($where));
```

FQL evaluates `AND` / `OR` left to right without precedence. To add `AND c` to
`a OR b`, wrap the original group — it is printed in parentheses:

```php
$group = new ConditionGroupNode([
    ['logical' => LogicalOperator::AND, 'condition' => $ast->where->conditions],
    ['logical' => LogicalOperator::AND, 'condition' => $condition],
], $p);
// WHERE a = 1 OR b = 2  →  WHERE (a = 1 OR b = 2) AND stock > 0
```

**Remove a condition / a clause**

```php
$printer->print($ast->withWhere($ast->where->withConditions($ast->where->conditions->withoutEntry(1))));
$printer->print($ast->withWhere(null));
```

**Change the source**

```php
$source = $ast->from->source;                       // FileQueryNode
$fileQuery = $source->fileQuery->withQuery('SHOP.ITEM')->withParam('enclosure', "'");
$printer->print($ast->withFrom($ast->from->withSource($source->withFileQuery($fileQuery))));
// FROM csv(./a.csv, delimiter: ";").SHOP  →  FROM csv(./a.csv, delimiter: ";", enclosure: "'").SHOP.ITEM
```

Without a `raw` text the source is written from the `FileQuery` with every non-default
parameter; named parameters are used when the original source used them (or when a
parameter has no position). Pass `$raw` (e.g. `$fileQuery->toFql(true)`) to choose the text.

**Hide a column**

```php
// an explicit field: remove it
$printer->print($ast->withFields([$ast->fields[0], $ast->fields[2]]));
// a column coming from *: exclude it
$hidden = new SelectFieldNode(new ColumnReferenceNode('secret', $p), null, true, $p);
$printer->print($ast->withFields([...$ast->fields, $hidden]));
// SELECT * FROM …  →  SELECT * EXCLUDE secret FROM …
```

Excluded fields must come after all selected ones (`EXCLUDE` applies to every field after it).

**Group and count**

```php
$count = new SelectFieldNode(new FunctionCallNode('COUNT', [new StarNode($p)], false, $p), 'count', false, $p);
$printer->print($ast
    ->withFields([new SelectFieldNode(new ColumnReferenceNode('brand', $p), null, false, $p), $count])
    ->withGroupBy(new GroupByClauseNode([new ColumnReferenceNode('brand', $p)], $p))
    ->withOrderBy(new OrderByClauseNode([new OrderByItemNode(new ColumnReferenceNode('count', $p), Sort::DESC, $p)], $p)));
```

## Source spans

Every node produced by the parser carries its source range, `span(): ?Span`
(interface `FQL\Sql\Ast\HasSpan`, implemented by every node class of the library).
`FQL\Sql\Token\Span` is a half-open byte range `[start, end)` of the SQL string:

```php
$span = $ast->where->span();          // Span
$span->start;                         // byte offset of WHERE
$span->end;                           // byte offset after the last condition
$span->slice($sql);                   // "WHERE price > 100"
```

A span starts at the node's first token and ends after its last token, so it never
includes surrounding whitespace or comments. Worth knowing:

| Node | Span |
|------|------|
| clause nodes | from the clause keyword (`FROM`, `LEFT OUTER JOIN`, `WHERE`, `UNION ALL`, …) to the end of the clause |
| `FromClauseNode` of `DESCRIBE` | from `DESCRIBE` |
| `SelectStatementNode` | from `EXPLAIN` / `WITH` / `SELECT` / `DESCRIBE` to the end, unions included |
| `BinaryOpNode`, `ConditionExpressionNode` | from the start of the left operand (`position` points at the operator / the left operand) |
| `ConditionGroupNode` | the conditions; a nested group includes its parentheses |
| `CommonTableExpressionNode` | `name AS ( … )` |
| `CollectObjectExpressionNode` | the items inside `COLLECT_OBJECT( … )` |
| `LimitClauseNode` | `LIMIT n OFFSET m` when written together; only the part written last when another clause lies between `LIMIT` and `OFFSET` |

Offsets are bytes (like `Position::$offset`); convert them for editors that count UTF-16
units. Nodes built in code have no span. `TokenStream::spanFrom()` and
`TokenStream::previous()` give custom parsers the same information.

## Fresh printing

`FQL\Sql\Printer\FqlPrinter` writes any node as single-line FQL without a source:

```php
use FQL\Sql\Printer\FqlPrinter;
use FQL\Sql\Printer\KeywordCase;

(new FqlPrinter())->print($ast);                       // SELECT … FROM … WHERE …
(new FqlPrinter(KeywordCase::LOWER))->print($condition);
FqlPrinter::identifier('Kód objednávky');              // `Kód objednávky`
FqlPrinter::columnReference('Kód.items[].name');       // `Kód`.items[].name
FqlPrinter::stringLiteral('say "hi"');                 // 'say "hi"'
```

The output always parses back to the same tree:

- identifiers are backticked when the tokenizer would not read them as one plain
  identifier (spaces, diacritics, keywords); column paths segment by segment;
- literals keep their `raw` spelling when it still matches the value; otherwise strings
  use double quotes (single quotes when the value contains `"`), numbers have no
  exponent, booleans and `NULL` are keywords;
- a column on the right side of a condition stays a column; arithmetic uses operators
  (`price * 2`); `ORDER BY` writes only `DESC`;
- file queries keep their raw text when it matches, otherwise they are written from
  their `FileQuery`.

`SqlFormatter` uses the same printing for expressions, so formatted queries keep
backticked identifiers and literals and parse back unchanged. `ExpressionCompiler`
(which also names result columns of unaliased expressions) is unchanged.

## Writing file queries

`FQL\Query\FileQuery` writes every parameter that differs from the format default:

```php
$fq = new FileQuery('csv(./a.csv)');
(string) $fq->withParam('delimiter', ';');                    // csv(./a.csv, "utf-8", ";")
(string) $fq->withParam('enclosure', "'");                    // csv(./a.csv, enclosure: "'")
$fq->withParam('delimiter', ';')->toFql(true);                // csv(./a.csv, delimiter: ";")
(string) $fq->withParams(['delimiter' => '|', 'bom' => '1']); // csv(./a.csv, delimiter: "|", bom: "1")
(string) FileQuery::fromParts('xml', './feed.xml', ['encoding' => 'windows-1250'], 'SHOP.ITEM');
// xml(./feed.xml, "windows-1250").SHOP.ITEM
```

`__toString()` stays positional when every non-default parameter has a position
(`encoding`, `delimiter`, `useHeader` for CSV; `encoding` for XML; `format` for LOG) and
switches to named parameters otherwise (`enclosure`, `bom`, LOG `pattern`). Parameters the
format does not know are not written. `Format::getParamNames()` and
`Format::getPositionalParamNames()` list them per format.

Parts FQL cannot express throw `FQL\Exception\FileQueryException` with a message that
names the part:

- a parameter value containing `"`;
- a file path with characters outside letters, digits, whitespace, `_ . - /`
  (e.g. `C:\data`, `,`, parentheses);
- a path in file with characters outside letters, digits, `_ - . *` (e.g. `@id`, spaces);
- a format without a file, or a format name that is not 2–8 letters.

## Errors

`FQL\Sql\Printer\UnprintableNodeException` (an `FQL\Exception\InvalidArgumentException`,
with the offending node in `$node`) is thrown for trees FQL cannot express, e.g.:

- an identifier or alias containing a backtick, a string containing both `'` and `"`;
- arithmetic that needs parentheses (`(a + b) * c`, `a - (b - c)`) or a signed number right after `*`;
- arithmetic inside `IN ( … )`, `BETWEEN` or `CAST`;
- an included SELECT field after an excluded one;
- an empty `GROUP BY` / `ORDER BY`, a `CASE` without `WHEN`, `CAST` to `TRUE` / `FALSE`;
- a condition used as a value, a function named like a keyword.

## Guarantees and limitations

- `print(original())` returns the original SQL byte for byte, for any valid input.
- `print($edited)` parses back to a tree equal to `$edited`, up to spellings the AST does
  not record: positions and spans, literal / file query `raw`, backticks around column
  path segments, and the logical operator of the first condition of a group (never printed).
- A changed `LIMIT` is written as `LIMIT n OFFSET m` (`LIMIT 5, 10` becomes `LIMIT 5 OFFSET 10`).
- A repeated clause (two `WHERE`s — only the last one applies) keeps the overridden copy
  when the clause is changed and drops both when it is removed.
- An item moved to another position keeps its text and trailing comments; comments on the
  lines before it may be dropped.
- Unions added with `withUnions()` are written one after another
  (`A UNION B UNION C`), which the parser reads as nested (`A UNION (B UNION C)`).
- A column named like a keyword (`desc`) is backticked when its node is printed fresh.

## Next steps
- [Opening Files](opening-files.md)
- [Fluent API](fluent-api.md)
- [File Query Language](file-query-language.md)
- [Fetching Data](fetching-data.md)
- [Query Life Cycle](query-life-cycle.md)
- [Query Inspection and Benchmarking](query-inspection-and-benchmarking.md)
- [API Reference](api-reference.md)
