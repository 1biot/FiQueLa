<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exception;
use FQL\Interface;
use FQL\Sql\Sql;
use FQL\Results;
use FQL\Results\ResultsProvider;
use FQL\Results\Stream;

class Debugger
{
    private static ?float $lastSplitTime = null;
    private static ?float $lastMemoryPeakUsage = null;

    public static function start(): void
    {
        if (!defined('DEBUGGER_START')) {
            define('DEBUGGER_START', microtime(true));
            define('DEBUGGER_MEMORY_START', (float) self::memoryPeakUsage());
            self::echoSection('Debugger started', 'magenta');
            self::split(false);
        }
    }

    public static function split(bool $header = true): void
    {
        if (!defined('DEBUGGER_START')) {
            return;
        }

        if ($header) {
            self::echoLine(self::echoYellow(self::echoBold('>>> SPLIT TIME <<<')), 0);
        }

        self::memoryDebug();

        $start = constant('DEBUGGER_START');
        $startMemoryPeak = constant('DEBUGGER_MEMORY_START');
        $end = microtime(true);
        $actualMemoryPeakUsage = (float) self::memoryPeakUsage();
        $lastSplitTime = self::$lastSplitTime ?? $start;
        $lastMemoryPeakUsage = self::$lastMemoryPeakUsage ?? $startMemoryPeak;

        $time = round(($end - $lastSplitTime) * 1e6); // µs
        $memoryPeakUsageDiff = $actualMemoryPeakUsage - $lastMemoryPeakUsage;
        self::echoLineNameValue('Execution time (s)', $time / 1000 / 1000);
        self::echoLineNameValue('Execution time (ms)', $time / 1000);
        self::echoLineNameValue('Execution time (µs)', $time);
        self::echoLineNameValue('Execution memory peak usage (MB)', $memoryPeakUsageDiff);

        self::$lastSplitTime = $end;
        self::$lastMemoryPeakUsage = $actualMemoryPeakUsage;
    }

    public static function end(): void
    {
        self::echoSection('Debugger ended', 'magenta');
        $start = constant('DEBUGGER_START');
        $end = microtime(true);

        $time = round(($end - $start) * 1e6); // µs
        self::memoryDebug();
        self::echoLineNameValue('Final execution time (s)', $time / 1000 / 1000);
        self::echoLineNameValue('Final execution time (ms)', $time / 1000);
        self::echoLineNameValue('Final execution time (µs)', $time);
    }

    public static function memoryUsage(bool $realUsage = false): string
    {
        return sprintf(
            '%s (%s)',
            round(memory_get_usage($realUsage) / 1024 / 1024, 4),
            $realUsage ? 'real' : 'emalloc'
        );
    }

    public static function memoryPeakUsage(bool $realUsage = false): string
    {
        return sprintf(
            '%s (%s)',
            round(memory_get_peak_usage($realUsage) / 1024 / 1024, 4),
            $realUsage ? 'real' : 'emalloc'
        );
    }

    public static function memoryDebug(int $beginCharRepeat = 1): void
    {
        self::echoLineNameValue('Memory usage (MB)', self::memoryUsage(), $beginCharRepeat);
        self::echoLineNameValue('Memory peak usage (MB)', self::memoryPeakUsage(), $beginCharRepeat);
        self::echoLine(self::echoYellow(self::echoBold('------------------------------')), 0);
    }

    public static function dump(mixed $var): void
    {
        dump($var);
    }

    public static function inspectQuery(Interface\Query $query, bool $listResults = false): void
    {
        self::echoSection('Inspecting query', 'blue');
        self::echoSection('SQL query');
        self::queryToOutput((string) $query);

        $results = $query->execute();
        self::echoSection('Results');
        self::echoLineNameValue('Result class', $results::class);
        self::echoLineNameValue('Results size memory (KB)', round(strlen(serialize($results)) / 1024, 2));
        self::echoLineNameValue('Result exists', $results->exists() ? Enum\Type::TRUE->value : Enum\Type::FALSE->value);
        self::echoLineNameValue('Result count', $results->count());
        if (!$results->exists()) {
            self::split();
            return;
        }

        if ($listResults) {
            self::echoSection('Fetch all rows');
            self::dump(iterator_to_array($results->fetchAll()));
        } else {
            self::echoSection('Fetch first row');
            self::dump($results->fetch());
        }

        self::split();
    }

    public static function inspectSql(string $sql): Interface\Query
    {
        self::echoSection('Inspect stream SQL', 'blue');
        self::echoSection('Original SQL query');
        self::queryToOutput($sql);
        return Provider::fql($sql);
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public static function inspectStreamSql(Interface\Stream $stream, string $sql): Query
    {
        self::echoSection('Inspect stream SQL', 'blue');
        self::echoSection('Original SQL query');
        self::queryToOutput($sql);

        /** @var Query $query */
        $query = (new Sql(trim($sql)))
            ->parseWithQuery($stream->query());

        self::inspectQuery($query);
        return $query;
    }

    public static function benchmarkQuery(Interface\Query $query, int $iterations = 2500): void
    {
        self::echoSection('Benchmark Query', 'blue');
        self::echoLine(sprintf('%s iterations', number_format($iterations, 0, ',', ' ')));

        self::echoSection('SQL query');
        self::queryToOutput((string) $query);

        self::benchmarkStream($query, $iterations);
        self::benchmarkProxy($query, $iterations);
    }

    private static function benchmarkStream(Interface\Query $query, int $iterations = 2500): void
    {
        $results = $query->execute(Stream::class);
        self::echoSection('STREAM BENCHMARK');
        self::echoLineNameValue('Size (KB)', round(strlen(serialize($results)) / 1024, 2));
        self::echoLineNameValue('Count', $results->count());

        self::iterateResults($results, $iterations);
        self::split();
    }

    private static function benchmarkProxy(Interface\Query $query, int $iterations = 2500): void
    {
        $results = $query->execute(Results\InMemory::class);
        self::echoSection('IN_MEMORY BENCHMARK');
        self::echoLineNameValue('Size (KB)', round(strlen(serialize($results)) / 1024, 2));
        self::echoLineNameValue('Count', $results->count());

        self::iterateResults($results, $iterations);
        self::split();
    }

    private static function iterateResults(ResultsProvider $results, int $iterations = 2500): void
    {
        $counter = 0;
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($results->getIterator() as $row) {
                $counter++;
            }
        }
        self::echoLineNameValue('Iterated results', number_format($counter, 0, ',', ' '));
    }

    public static function echoSection(string $text, ?string $color = 'cyan'): void
    {
        $text = '### ' . $text . ': ###';
        $colorCallback = match ($color) {
            'red' => [self::class, 'echoRed'],
            'yellow' => [self::class, 'echoYellow'],
            'green' => [self::class, 'echoGreen'],
            'blue' => [self::class, 'echoBlue'],
            'magenta' => [self::class, 'echoMagenta'],
            'white' => [self::class, 'echoWhite'],
            'black' => [self::class, 'echoBlack'],
            'gray' => [self::class, 'echoGray'],
            'lightRed' => [self::class, 'echoLightRed'],
            'lightGreen' => [self::class, 'echoLightGreen'],
            'lightYellow' => [self::class, 'echoLightYellow'],
            'lightBlue' => [self::class, 'echoLightBlue'],
            default => [self::class, 'echoCyan']
        };
        self::echoLine($colorCallback(self::echoBold(str_repeat('=', strlen($text)))), 0);
        self::echoLine($colorCallback(self::echoBold($text)), 0);
        self::echoLine($colorCallback(self::echoBold(str_repeat('=', strlen($text)))), 0);
    }

    public static function echoLineNameValue(string $name, mixed $value, int $beginCharRepeat = 1): void
    {
        self::echoLine(sprintf('%s: %s', self::echoBold($name), self::echoCyan($value)), $beginCharRepeat);
    }

    public static function echoLine(string $text, int $beginCharRepeat = 1): void
    {
        echo sprintf('%s%s', str_repeat('>', $beginCharRepeat) . ($beginCharRepeat ? ' ' : ''), $text) . PHP_EOL;
    }

    public static function queryToOutput(string $query): void
    {
        echo '> ' . str_replace(PHP_EOL, PHP_EOL . '> ', self::highlightSQL($query)) . PHP_EOL;
    }

    public static function highlightSQL(string $sql): string
    {
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'ORDER', 'GROUP', 'BY', 'HAVING', 'DISTINCT',
            'LIMIT', 'OFFSET', 'JOIN', 'ON', 'AS', 'AND', 'OR', 'DESC', 'LIKE', 'XOR',
            'ASC', 'IN', 'IS', 'NOT', 'NULL', 'SHUFFLE', 'NATURAL', 'LEFT', 'INNER'
        ];

        $fromPattern = FileQuery::getRegexp();
        // Function: Uppercase letters, numbers and underscores, at least 2 characters, cannot start/end with underscore
        $functionPattern = '([A-Z0-9_]{2,})(?<!_)\\((.*?)\\)';

        // Tokenization with respect to brackets, quotes and multi-line input ((\[([a-z]+:\/\/)?(.*)])(.*))
        $regex = '/
        \b' . $functionPattern . ' # function
        | ' . $fromPattern . ' # FROM
        | (\'[^\']*\' # simple quoted string
        | "[^"]*" # double quotes
        | [(),] # bracket or comma
        | \b(' . implode('|', $keywords) . ')\b # operators
        | [^\s\'"(),]+ # other than spaces and quoteshe
        )/xi';

        // Preserving multi-line structure
        $lines = explode(PHP_EOL, $sql);
        $highlightedLines = [];
        foreach ($lines as $line) {
            // Extract initial spaces or tabs
            preg_match('/^(\s*)/', $line, $matches);
            $indentation = $matches[1] ?? '';

            preg_match_all($regex, $line, $matches);
            $tokens = array_filter($matches[0]);

            $hasStream = false;
            $highlightedTokens = array_map(
                function ($token) use ($keywords, $functionPattern, $fromPattern, &$hasStream) {
                    if (trim($token) === '') {
                        return '';
                    }

                    // Keywords
                    if (in_array(strtoupper($token), $keywords)) {
                        if (in_array(strtoupper($token), ['FROM', 'JOIN'])) {
                            $hasStream = true;
                        }
                        return self::echoBlue(self::echoBold($token));
                    }

                    // FROM [].data.item
                    if ($hasStream && preg_match('/^' . $fromPattern . '$/', $token, $matches)) {
                        $ext = $matches['e'] ?? '';
                        $file = $matches['fp'] ?? '';
                        $query = $matches['q'] ?? '';
                        if ($file === '' && $query === '') {
                            return '';
                        }

                        $highlightedString = '';
                        if ($ext !== '') {
                            $highlightedString = self::echoMagenta(self::echoBold(sprintf('[%s]', $ext)));
                        }

                        if ($file !== '') {
                            $highlightedString .= self::echoLightRed(self::echoBold("({$file})"));
                        }

                        if ($query !== '') {
                            $highlightedString .= self::echoCyan(self::echoBold($query));
                        }

                        $hasStream = false;
                        return $highlightedString;
                    }

                    // Functions
                    if (preg_match('/\b' . $functionPattern . '/', $token, $matches)) {
                        $functionName = $matches[1];
                        $innerContent = $matches[2];

                        // Highlight parameters in content
                        $highlightedContent = implode(
                            ', ',
                            array_map(
                                fn ($param) => self::isQuoted(trim($param))
                                    ? self::echoGreen(trim($param))
                                    : trim($param),
                                explode(',', $innerContent)
                            )
                        );

                        return self::echoRed($functionName) . "({$highlightedContent})";
                    }

                    // Numbers
                    if (is_numeric($token)) {
                        return self::echoMagenta($token);
                    }

                    // Strings
                    if (preg_match("/^['\"].*['\"]$/", $token)) {
                        return self::echoGreen($token);
                    }

                    // Operators
                    if (preg_match('/^(>=|<=|<>|!=|=|<|>|!==|==)$/', $token)) {
                        return self::echoYellow(self::echoUnderline($token));
                    }

                    // Return token unchanged
                    return $token;
                },
                $tokens
            );

            $highlightedLines[] = $indentation . implode(' ', $highlightedTokens);
        }

        return implode(PHP_EOL, $highlightedLines);
    }

    private static function isQuoted(string $input): bool
    {
        return preg_match('/^".*"$/', $input) === 1 || preg_match('/^\'.*\'$/', $input) === 1;
    }

    public static function echoBold(string $text): string
    {
        return "\033[1m{$text}\033[0m";
    }

    public static function echoUnderline(string $text): string
    {
        return "\033[4m{$text}\033[0m";
    }

    public static function echoItalic(string $text): string
    {
        return "\033[3m{$text}\033[0m";
    }

    public static function echoBlue(string $text): string
    {
        return "\033[0;34m{$text}\033[0m";
    }

    public static function echoCyan(string $text): string
    {
        return "\033[0;36m{$text}\033[0m";
    }

    public static function echoGreen(string $text): string
    {
        return "\033[0;32m{$text}\033[0m";
    }

    public static function echoRed(string $text): string
    {
        return "\033[0;31m{$text}\033[0m";
    }

    public static function echoYellow(string $text): string
    {
        return "\033[0;33m{$text}\033[0m";
    }

    public static function echoMagenta(string $text): string
    {
        return "\033[0;35m{$text}\033[0m";
    }

    public static function echoWhite(string $text): string
    {
        return "\033[0;37m{$text}\033[0m";
    }

    public static function echoBlack(string $text): string
    {
        return "\033[0;30m{$text}\033[0m";
    }

    public static function echoGray(string $text): string
    {
        return "\033[0;90m{$text}\033[0m";
    }

    public static function echoLightRed(string $text): string
    {
        return "\033[0;91m{$text}\033[0m";
    }

    public static function echoLightGreen(string $text): string
    {
        return "\033[0;92m{$text}\033[0m";
    }

    public static function echoLightYellow(string $text): string
    {
        return "\033[0;93m{$text}\033[0m";
    }

    public static function echoLightBlue(string $text): string
    {
        return "\033[0;94m{$text}\033[0m";
    }

    public static function echoException(\Exception $e): void
    {
        self::echoSection($e::class, 'red');
        self::echoLine(self::echoRed(self::echoBold($e->getMessage())));
        array_map(
            fn ($line) => self::echoLine(self::echoYellow(self::echoItalic($line))),
            explode(PHP_EOL, $e->getTraceAsString())
        );
    }
}
