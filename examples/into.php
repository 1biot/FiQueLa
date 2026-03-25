<?php

use FQL\Query;
use FQL\Enum\Operator as Op;
use FQL\Query\FileQuery;

require __DIR__ . '/bootstrap.php';

$tempDir = sys_get_temp_dir() . '/fiquela-into-example-' . uniqid();

try {
    mkdir($tempDir, 0775, true);

    $sourcePath = realpath(__DIR__ . '/data/products.json');
    if ($sourcePath === false) {
        throw new RuntimeException('Unable to resolve source file path.');
    }

    $csvTarget = $tempDir . '/products.csv';
    $query = Query\Provider::fromFileQuery(sprintf('json(%s).data.products', $sourcePath))
        ->select('name', 'price')
        ->where('price', Op::GREATER_THAN_OR_EQUAL, 300)
        ->orderBy('price')->asc()
        ->into(new FileQuery(sprintf('csv(%s)', $csvTarget)));

    Query\Debugger::echoSection('Fluent query with INTO');
    Query\Debugger::inspectQuery($query, true);

    $intoFileQuery = $query->getInto();
    if ($intoFileQuery === null) {
        throw new RuntimeException('INTO clause was not parsed.');
    }

    $query->execute()->into($intoFileQuery);

    Query\Debugger::echoSection('INTO output verification');
    $verificationQuery = Query\Provider::fromFileQuery(sprintf('csv(%s).*', $csvTarget))
        ->select('name', 'price')
        ->orderBy('price')->asc();

    Query\Debugger::inspectQuery($verificationQuery, true);
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
} finally {
    if (is_dir($tempDir)) {
        $items = scandir($tempDir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $tempDir . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        rmdir($tempDir);
    }
}

Query\Debugger::end();
