<?php

namespace UQL\Query;

use UQL\Parser\Sql;
use UQL\Results\Cache;
use UQL\Results\ResultsProvider;
use UQL\Results\Stream;
use UQL\Stream\Json;
use UQL\Stream\JsonStream;
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

    public static function split(): void
    {
        if (!defined('DEBUGGER_START')) {
            return;
        }

        self::memoryDebug();

        $start = constant('DEBUGGER_START');
        $end = microtime(true);
        $lastSplitTime = self::$lastSplitTime ?? $start;

        $time = round(($end - $lastSplitTime) * 1e6); // µs
        self::echoLineNameValue('Execution time (s)', $time / 1000 / 1000);
        self::echoLineNameValue('Execution time (ms)', $time / 1000);
        self::echoLineNameValue('Execution time (µs)', $time);

        self::$lastSplitTime = $end;
    }

    public static function end(): void
    {
        self::dump('==============================');
        $start = constant('DEBUGGER_START');
        $end = microtime(true);

        $time = round(($end - $start) * 1e6); // µs
        self::echoLineNameValue('Final execution time (s)', $time / 1000 / 1000, 2);
        self::echoLineNameValue('Final execution time (ms)', $time / 1000, 2);
        self::echoLineNameValue('Final execution time (µs)', $time, 2);
    }

    public static function memoryUsage(bool $realUsage = false): void
    {
        self::echoLineNameValue(
            'Memory usage',
            sprintf(
                '%sMB (%s)',
                round(memory_get_usage($realUsage) / 1024 / 1024, 4),
                $realUsage ? 'real' : 'emalloc'
            )
        );
    }

    public static function memoryPeakUsage(bool $realUsage = false): void
    {
        self::echoLineNameValue(
            'Memory peak usage',
            sprintf(
                '%sMB (%s)',
                round(memory_get_peak_usage($realUsage) / 1024 / 1024, 4),
                $realUsage ? 'real' : 'emalloc'
            )
        );
    }

    public static function memoryDebug(): void
    {
        self::dump('------------------------------');
        self::memoryUsage();
        self::memoryPeakUsage();
        self::dump('------------------------------');
    }

    public static function dump(mixed $var): void
    {
        dump($var);
    }

    public static function inspectQuery(Query $query, bool $listResults = false): void
    {
        self::echoSection('SQL query');
        self::queryToOutput($query->test());

        $results = $query->execute();
        self::echoSection('Results');
        self::echoLineNameValue('Count', $results->count());
        if ($listResults) {
            self::dump('------------------');
            self::dump(iterator_to_array($results->fetchAll()));
        } else {
            self::echoSection('First row');
            self::dump($results->fetch());
        }

        self::split();
    }

    public static function inspectQuerySql(Xml|Json|JsonStream|Neon|Yaml $stream, string $sql): Query
    {
        self::echoSection('Original SQL query');
        self::queryToOutput($sql);

        $query = (new Sql())
            ->parse(trim($sql), $stream->query());

        self::inspectQuery($query);
        return $query;
    }

    public static function benchmarkQuery(Query $query, int $iterations = 2500): void
    {
        self::echoSection('Benchmark Query');
        self::echoLine(sprintf('%s iterations', number_format($iterations, 0, ',', ' ')));

        self::echoSection('SQL query');
        self::queryToOutput($query->test());

        self::benchmarkStream($query, $iterations);
        self::benchmarkProxy($query, $iterations);
    }

    private static function benchmarkStream(Query $query, int $iterations = 2500): void
    {
        $results = $query->execute(Stream::class);
        self::echoSection('STREAM BENCHMARK');
        self::echoLineNameValue('Size (KB)', round(strlen(serialize($results)) / 1024, 2));
        self::echoLineNameValue('Count', $results->count());

        self::iterateResults($results, $iterations);
        self::split();
    }

    private static function benchmarkProxy(Query $query, int $iterations = 2500): void
    {
        $results = $query->execute();
        self::echoSection('PROXY BENCHMARK');
        self::echoLineNameValue('Size (KB)', round(strlen(serialize($results)) / 1024, 2));
        self::echoLineNameValue('Count', $results->count());

        self::iterateResults($results, $iterations);
        self::split();
    }

    private static function iterateResults(ResultsProvider|Cache $results, int $iterations = 2500): void
    {
        $counter = 0;
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($results->getIterator() as $row) {
                $counter++;
            }
        }
        self::echoLineNameValue('Iterated results', number_format($counter, 0, ',', ' '));
    }

    private static function echoSection(string $text): void
    {
        $text = '*** ' . $text . ': ***';
        self::dump(str_repeat('=', strlen($text)));
        self::dump($text);
        self::dump(str_repeat('=', strlen($text)));
    }

    private static function echoLineNameValue(string $name, mixed $value, int $beginCharRepeat = 1): void
    {
        self::echoLine(sprintf('%s: %s', $name, $value), $beginCharRepeat);
    }

    private static function echoLine(string $text, int $beginCharRepeat = 1): void
    {
        echo sprintf('%s %s', str_repeat('>', $beginCharRepeat), $text) . PHP_EOL;
    }

    private static function queryToOutput(string $query): void
    {
        echo '>> ' . str_replace(PHP_EOL, PHP_EOL . '>> ', $query) . PHP_EOL;
    }
}
