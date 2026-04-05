<?php

require __DIR__ . '/bootstrap.php';

use FQL\Query;
use FQL\Stream;

try {
    // 1. Fluent API — nginx_combined (default)
    Query\Debugger::echoSection('Fluent API — nginx_combined (default)');

    $log = Stream\AccessLog::open('./examples/data/access-nginx-combined.log');
    $query = $log->query()
        ->select('host')
        ->select('method')
        ->select('path')
        ->select('status')
        ->select('responseBytes')
        ->select('time')
        ->select('user_agent');

    Query\Debugger::inspectQuery($query);

    // 2. Fluent API — apache_common
    Query\Debugger::echoSection('Fluent API — apache_common');

    $apacheLog = Stream\AccessLog::open('./examples/data/access-apache-common.log');
    $apacheLog->setFormat('apache_common');

    $query = $apacheLog->query()
        ->select('host')
        ->select('logname')
        ->select('user')
        ->select('method')
        ->select('path')
        ->select('status')
        ->select('responseBytes')
        ->select('time');

    Query\Debugger::inspectQuery($query);

    // 3. FQL — nginx_combined
    Query\Debugger::echoSection('FQL — nginx_combined');

    $sql = <<<SQL
SELECT host, method, path, status, responseBytes, time, referer, user_agent
FROM log(./examples/data/access-nginx-combined.log).*
WHERE status >= 200 AND status < 400
SQL;

    $fqlQuery = Query\Provider::fql($sql);
    Query\Debugger::inspectQuery($fqlQuery);

    // 4. FQL — apache_common with named param
    Query\Debugger::echoSection('FQL — apache_common (named param)');

    $sql = <<<SQL
SELECT host, user, method, path, status, responseBytes
FROM log(./examples/data/access-apache-common.log, format: "apache_common").*
SQL;

    $fqlQuery = Query\Provider::fql($sql);
    Query\Debugger::inspectQuery($fqlQuery);

    // 5. FQL — filter errors and group by status
    Query\Debugger::echoSection('FQL — group by status');

    $sql = <<<SQL
SELECT status, COUNT(status) AS count
FROM log(./examples/data/access-nginx-combined.log).*
GROUP BY status
ORDER BY status
SQL;

    $fqlQuery = Query\Provider::fql($sql);
    Query\Debugger::inspectQuery($fqlQuery);

    // 6. Malformed log — error handling
    Query\Debugger::echoSection('Malformed log — error handling');

    $malformed = Stream\AccessLog::open('./examples/data/access-malformed.log');
    $query = $malformed->query()
        ->select('host')
        ->select('status')
        ->select('_error')
        ->select('_raw');

    Query\Debugger::inspectQuery($query);

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
