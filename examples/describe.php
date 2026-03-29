<?php

use FQL\Query;
use FQL\Stream;

require __DIR__ . '/bootstrap.php';

try {
    $json = Stream\Provider::fromFile('./examples/data/products.json');

    $describe = $json->query()
        ->from('data.products')
        ->describe();

    Query\Debugger::echoSection('DESCRIBE via Fluent API');
    Query\Debugger::queryToOutput($describe);
    Query\Debugger::dump(iterator_to_array($describe->execute()->fetchAll()));

    $sql = <<<SQL
DESCRIBE csv(./examples/data/products-w-1250.csv, encoding: "windows-1250", delimiter: ";")
SQL;

    Query\Debugger::echoSection('DESCRIBE via FQL');
    $fqlQuery = Query\Provider::fql($sql);
    Query\Debugger::queryToOutput($fqlQuery);
    Query\Debugger::dump(iterator_to_array($fqlQuery->execute()->fetchAll()));

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
