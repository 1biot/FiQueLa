# Query Life Cycle

1) **FROM** and **JOIN**:
    - Data are loaded from the sources specified in the `FROM` clause.
    - If `JOIN` is present, data is combined based on the join conditions.
2) **WHERE**:
    - Filters rows based on conditions specified in the `WHERE` clause.
    - Filtering happens at the raw data level before any grouping or calculations.
3) **DISTINCT**:
    - If the `DISTINCT` clause is used, duplicate rows are removed based on the specified columns.
    - `DISTINCT` is skipped if a `GROUP BY` clause is present, as grouping inherently eliminates duplicates.
4) **GROUP BY**:
    - Data is grouped based on the columns specified in the `GROUP BY` clause.
    - This prepares grouped data for aggregate functions like `SUM`, `COUNT`, `AVG`, etc.
5) **SELECT**:
    - Columns, expressions, and aggregate functions defined in the `SELECT` clause are processed.
    - The output includes either aggregated data or individual columns.
6) **HAVING**:
    - Filters the results produced by the `SELECT` clause.
    - Primarily used to filter grouped data after aggregation.
7) **ORDER BY**:
    - Results are sorted based on the specified columns or expressions.
    - Sorting can be in ascending (`ASC`), descending (`DESC`) or shuffled (`SHUFFLE`) and naturally sorted (`NATSORT`) order.
8) **EXCLUDE**:
    - Excludes specified columns from the output.
9) **LIMIT** and **OFFSET**:
    - Limits the number of rows returned by the query using `LIMIT`.
    - If `OFFSET` is present, it skips a specified number of rows before returning the results.
10) **UNION** / **UNION ALL**:
    - If `UNION` or `UNION ALL` is present, results from union subqueries are appended after the main query pipeline.
    - Each union subquery executes its own independent pipeline (FROM → WHERE → SELECT → …).
    - `UNION` removes duplicate rows (using hash-based deduplication), `UNION ALL` keeps all rows.
    - The number of selected columns must match across all combined queries.

> ⚠️ Functions can only be utilized in the `SELECT` clause. To filter results based on a function's value, use the `HAVING`
clause, and to sort results, use the `ORDER BY` clause. In both `HAVING` and `ORDER BY`, the function can only be
referenced by its alias.

## EXPLAIN and EXPLAIN ANALYZE

You can inspect the query execution plan without running the query (`EXPLAIN`) or with real metrics (`EXPLAIN ANALYZE`).

- **EXPLAIN** returns a plan-only table with `null` metrics — useful for understanding which phases will run and in what order.
- **EXPLAIN ANALYZE** executes the query and collects real row counts, timings (`time_ms`, `duration_pct`), and peak memory usage (`mem_peak_kb`) for each phase.

Phases correspond to the lifecycle stages above: `stream`, `join`, `where`, `group`, `having`, `sort`, `limit`.
When unions are present, each union branch reports its own sub-phases (e.g. `union_stream`, `union_where`) followed
by a summary row (`union`). For multiple unions, phases are indexed (`union_1_stream`, `union_2_stream`, etc.).

For more details see [Fluent API — Explain](fluent-api.md#explain) and [FQL — Explain](file-query-language.md#10-explain).
___

**Example Query Execution**:

For the query:

```sql
SELECT
    name,
    SUM(sales) AS total_sales,
    ROUND(total_sales, 2) AS rounded_sales
EXCLUDE total_sales
FROM (customers.xml).customers.customer
WHERE age > 30
GROUP BY name
HAVING total_sales > 1000
ORDER BY total_sales DESC
LIMIT 10
UNION ALL
SELECT
    name,
    SUM(sales) AS total_sales,
    ROUND(total_sales, 2) AS rounded_sales
FROM (partners.xml).partners.partner
WHERE age > 25
GROUP BY name;
```

**Execution Order**:
1) **FROM** and **JOIN**:
    - Data are loaded from the file `customers.xml`. And using `customers.customer` as query path.
2) **WHERE**:
    - Filters rows where `age > 30`.
3) **GROUP BY**:
    - Groups data by the `name` column.
4) **SELECT**:
    - Processes grouped data and calculates `SUM(sales)` for each group.
5) **HAVING**:
    - Filters groups where `total_sales > 1000`.
6) **ORDER BY**:
    - Sorts results by `total_sales` in descending order.
7) **EXCLUDE**:
    - Excludes the `total_sales` column from the final output.
8) **LIMIT**:
    - Returns only the top 10 rows.
9) **UNION ALL**:
    - The union subquery executes its own pipeline (FROM → WHERE → GROUP BY → SELECT) against `partners.xml`.
    - All rows from the union subquery are appended to the main result (no deduplication with `UNION ALL`).

## Next steps
- [Opening Files](opening-files.md)
- [Fluent API](fluent-api.md)
- [File Query Language](file-query-language.md)
- [Fetching Data](fetching-data.md)
- Query Life Cycle
- [FiQueLa CLI](fiquela-cli.md)
- [Query Inspection and Benchmarking](query-inspection-and-benchmarking.md)

or go back to [README.md](../README.md).
