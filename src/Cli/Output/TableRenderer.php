<?php

namespace FQL\Cli\Output;

use FQL\Cli\Query\QueryResult;
use FQL\Query\Debugger;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class TableRenderer
{
    private int $valueTruncateLength;

    public function __construct(int $valueTruncateLength = 50)
    {
        $this->valueTruncateLength = $valueTruncateLength;
    }

    /**
     * Render a query result into a Symfony Console table section.
     *
     * @param ConsoleSectionOutput $section
     * @param QueryResult $result
     * @param int $currentPage
     * @param int $totalPages
     * @param int $itemsPerPage
     */
    public function render(
        ConsoleSectionOutput $section,
        QueryResult $result,
        int $currentPage,
        int $totalPages,
        int $itemsPerPage
    ): void {
        $section->clear();

        $table = new Table($section);
        $table->setHeaders($result->headers)
            ->setRows($this->formatRows($result->data))
            ->setHeaderTitle(
                sprintf('Page %d/%d', $currentPage, $totalPages)
            )->setFooterTitle(
                sprintf(
                    'Showing %d-%d from %d rows',
                    ($currentPage - 1) * $itemsPerPage + 1,
                    min($currentPage * $itemsPerPage, $result->totalCount),
                    $result->totalCount
                )
            )->render();

        $section->writeln(
            sprintf(
                '<info>%s sec, memory %s, memory (peak) %s</info>',
                number_format($result->elapsed, 4),
                Debugger::memoryUsage(),
                Debugger::memoryPeakUsage()
            )
        );
    }

    /**
     * Render a single-page result (no pagination info needed in header).
     */
    public function renderSinglePage(
        ConsoleSectionOutput $section,
        QueryResult $result
    ): void {
        $section->clear();

        $table = new Table($section);
        $table->setHeaders($result->headers)
            ->setRows($this->formatRows($result->data))
            ->setHeaderTitle(
                sprintf('Page 1/1')
            )->setFooterTitle(
                sprintf(
                    'Showing 1-%d from %d rows',
                    $result->totalCount,
                    $result->totalCount
                )
            )->render();

        $section->writeln(
            sprintf(
                '<info>%s sec, memory %s, memory (peak) %s</info>',
                number_format($result->elapsed, 4),
                Debugger::memoryUsage(),
                Debugger::memoryPeakUsage()
            )
        );
    }

    /**
     * Highlight text in the rendered section content.
     */
    public function highlightText(ConsoleSectionOutput $section, string $searchTerm): void
    {
        if ($searchTerm === '') {
            return;
        }

        $content = $section->getContent();
        $content = str_replace(
            $searchTerm,
            "<options=bold,underscore;fg=yellow>{$searchTerm}</>",
            $content
        );
        $section->overwrite($content);
    }

    /**
     * Format rows for table display — truncate values, encode arrays, handle nulls.
     *
     * @param array<int, array<string, mixed>> $data
     * @return array<int, array<int, string>>
     */
    public function formatRows(array $data): array
    {
        return array_map(function (array $row) {
            $values = array_values($row);
            return array_map(function ($value): string {
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
                } elseif ($value === null) {
                    $value = 'null';
                } else {
                    $value = (string) $value;
                }

                if (mb_strlen($value) <= $this->valueTruncateLength) {
                    return $value;
                }

                return mb_substr($value, 0, $this->valueTruncateLength) . '...';
            }, $values);
        }, $data);
    }

    /**
     * Format human-readable file size.
     */
    public static function humanFilesize(int $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        $factor = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return round($bytes / (1024 ** $factor), $decimals) . ' ' . $units[(int) $factor];
    }
}
