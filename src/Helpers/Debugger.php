<?php

namespace UQL\Helpers;

use UQL\Parser\Sql;
use UQL\Query\Query;
use UQL\Stream\Json;
use UQL\Stream\Neon;
use UQL\Stream\Xml;
use UQL\Stream\Yaml;

class Debugger
{
    private static ?float $lastSplitTime = null;

    public static function start(): void
    {
        if (!defined('DEBUGGER_START')) {
            define('DEBUGGER_START', microtime(true));
        }
    }

    public static function end(): void
    {
        if (!defined('DEBUGGER_START')) {
            return;
        }

        self::dump('------------------------------');
        self::debug();
        self::dump('------------------------------');
        $start = constant('DEBUGGER_START');
        $end = microtime(true);

        $splitTime = $end - $start - (self::$lastSplitTime ?? 0);
        self::$lastSplitTime = $end - $start;
        $units = ['ms', 's'];
        $time = round(($splitTime - floor($splitTime)) * 1e6); // µs
        self::dump('Execution time (µs): ' . $time);
        foreach ($units as $unit) {
            if ($time < 1000) {
                break;
            }

            $time /= 1000;
            self::dump(sprintf('Execution time (%s): %s', $unit, $time));
        }
    }

    public static function finish(): void
    {
        self::dump('------------------------------');
        $start = constant('DEBUGGER_START');
        $end = microtime(true);
        $splitTime = $end - $start;
        self::dump('Final execution time (µs): ' . round(($splitTime - floor($splitTime)) * 1e6));
        self::dump('Final execution time (ms): ' . round(($splitTime - floor($splitTime)) * 1e6 / 1000));
    }

    public static function memoryUsage(bool $realUsage = false): void
    {
        self::dump(
            sprintf(
                'Memory usage: %sMB (%s)',
                round(memory_get_usage($realUsage) / 1024 / 1024, 4),
                $realUsage ? 'real' : 'emalloc'
            )
        );
    }

    public static function memoryPeakUsage(bool $realUsage = false): void
    {
        self::dump(
            sprintf(
                'Memory peak usage: %sMB (%s)',
                round(memory_get_peak_usage($realUsage) / 1024 / 1024, 4),
                $realUsage ? 'real' : 'emalloc'
            )
        );
    }

    public static function debug(): void
    {
        self::memoryUsage();
        self::memoryPeakUsage();
        self::memoryUsage(true);
        self::memoryPeakUsage(true);
    }

    public static function dump(mixed $var): void
    {
        dump($var);
    }

    public static function inspectQuery(Query $query, bool $results = false): void
    {
        self::dump('------------------');
        self::dump('### SQL query: ###');
        self::dump('------------------');
        self::queryToOutput($query->test());
        self::dump('----------------');
        self::dump('### Results: ###');
        self::dump('----------------');
        self::dump(sprintf('### Count: %s', $query->count()));
        self::dump('------------------');
        self::dump('### First row: ###');
        self::dump('------------------');
        self::dump($query->fetch());
        if ($results) {
            self::dump('------------------');
            self::dump(iterator_to_array($query->fetchAll()));
        }

        self::end();
    }

    public static function inspectQuerySql(Xml|Json|Neon|Yaml $stream, string $sql): void
    {
        self::dump('---------------------------');
        self::dump('### Original SQL query: ###');
        self::dump('---------------------------');
        self::queryToOutput($sql);

        $query = (new Sql())
            ->parse(trim($sql), $stream->query());

        self::inspectQuery($query);
    }

    private static function queryToOutput(string $query): void
    {
        echo '>>> ' . str_replace("\n", "\n>>> ", $query) . PHP_EOL;
    }
}
