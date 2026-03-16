<?php

namespace FQL\Cli\Interactive;

class HistoryManager
{
    private string $historyFile;
    private int $maxEntries;

    public function __construct(string $historyFile, int $maxEntries = 50)
    {
        $this->historyFile = $historyFile;
        $this->maxEntries = $maxEntries;
    }

    public function getHistoryFile(): string
    {
        return $this->historyFile;
    }

    /**
     * Load history from file into readline.
     */
    public function load(): void
    {
        if (!function_exists('readline_read_history')) {
            return;
        }

        if (file_exists($this->historyFile)) {
            readline_read_history($this->historyFile);
        }
    }

    /**
     * Save a query to history (with deduplication).
     */
    public function save(string $query): void
    {
        if (!function_exists('readline_add_history')) {
            return;
        }

        $lastQuery = $this->getLastEntry();
        if ($lastQuery === $query) {
            return;
        }

        readline_add_history($query);

        if (function_exists('readline_write_history')) {
            readline_write_history($this->historyFile);
        }

        $this->truncate();
    }

    /**
     * Replace the history file with API history entries.
     *
     * @param string[] $queries List of queries from API history
     */
    public function replaceWithApiHistory(array $queries): void
    {
        $dir = dirname($this->historyFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $normalizedQueries = $this->normalizeQueries($queries);

        if (function_exists('readline_clear_history') && function_exists('readline_add_history')) {
            readline_clear_history();

            foreach ($normalizedQueries as $query) {
                readline_add_history($query);
            }

            if (function_exists('readline_write_history')) {
                readline_write_history($this->historyFile);
            }

            $this->truncate();
            return;
        }

        // Fallback when readline functions are unavailable
        $content = "_HiStOrY_V2_\n" . implode("\n", $normalizedQueries);
        if ($normalizedQueries !== []) {
            $content .= "\n";
        }
        file_put_contents($this->historyFile, $content);
    }

    /**
     * Get the last entry from the history file.
     */
    private function getLastEntry(): ?string
    {
        if (!file_exists($this->historyFile)) {
            return null;
        }

        $lines = file($this->historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return null;
        }

        $decoded = array_map([$this, 'decodeReadlineEntry'], $lines);
        return end($decoded) ?: null;
    }

    /**
     * Truncate history to keep only the last N entries.
     */
    private function truncate(): void
    {
        if (!file_exists($this->historyFile)) {
            return;
        }

        $lines = file($this->historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return;
        }

        // Preserve the _HiStOrY_V2_ header
        $header = null;
        if (isset($lines[0]) && str_starts_with($lines[0], '_HiStOrY_V2_')) {
            $header = array_shift($lines);
        }

        if (count($lines) > $this->maxEntries) {
            $lines = array_slice($lines, -$this->maxEntries);
        }

        $content = '';
        if ($header !== null) {
            $content = $header . "\n";
        }
        $content .= implode("\n", $lines) . "\n";

        file_put_contents($this->historyFile, $content);
    }

    /**
     * Decode readline octal escape sequences.
     */
    private function decodeReadlineEntry(string $escapedString): string
    {
        return (string) preg_replace_callback('/\\\\([0-7]{1,3})/', function ($matches) {
            return chr((int) octdec($matches[1]));
        }, $escapedString);
    }

    /**
     * @param string[] $queries
     * @return string[]
     */
    private function normalizeQueries(array $queries): array
    {
        $normalized = [];

        foreach ($queries as $query) {
            $singleLine = preg_replace('/\s+/u', ' ', trim($query));
            if ($singleLine === null || $singleLine === '') {
                continue;
            }

            $normalized[] = $singleLine;
        }

        return $normalized;
    }
}
