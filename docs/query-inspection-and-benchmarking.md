# Query Inspection and Benchmarking

If you want use inspecting and benchmarking queries, you need to use `FQL\Query\Debugger` class. Dumping variables and
cli output require `tracy/tracy` package if you are not using it, you can install it by:

```bash
composer require --dev tracy/tracy
```

Start debugger at the beginning of your script.

```php
use FQL\Query\Debugger;

Debugger::start();
```

## Inspect Queries
You can inspect your query for mor information about execution time, memory usage, SQL query and results.

```php
use FQL\Stream\Xml;

$ordersFile = Xml::open(__DIR__ . '/data/orders.xml');
$query = $ordersFile->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order');

Debugger::inspectQuery($query);
```

Or inspect query string which shows different between input SQL and applied SQL

```php
Debugger::inspectQuerySql(
    $ordersFile,
    "SELECT id, user_id, total_price FROM orders.order"
);
```

## Benchmarking

You can benchmark your queries and test their performance through the number of iterations.

```php
use FQL\Stream\Xml;

$query = Xml::open(__DIR__ . '/data/orders.xml')->query()
    ->select('id')->as('orderId')
    ->select('user_id')->as('userId')
    ->select('total_price')->as('totalPrice')
    ->from('orders.order')

Debugger::benchmarkQuery($query, 1000);
```

## Final results

This method stops the debugger and outputs the final results.

```php
Debugger::end();
```

For more information about inspecting queries and benchmarking, see the [examples](../README.md#6-examples)

## Next steps
- [Opening Files](opening-files.md)
- [Fluent API](fluent-api.md)
- [File Query Language](file-query-language.md)
- [Fetching Data](fetching-data.md)
- [Query Life Cycle](query-life-cycle.md)
- [FiQueLa CLI](fiquela-cli.md)
- Query Inspection and Benchmarking

or go back to [README.md](../README.md).
