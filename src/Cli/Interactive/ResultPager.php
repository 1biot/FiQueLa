<?php

namespace FQL\Cli\Interactive;

use FQL\Cli\Output\TableRenderer;
use FQL\Cli\Query\QueryExecutorInterface;
use Nette\Utils\Paginator;
use Symfony\Component\Console\Output\ConsoleOutput;

class ResultPager
{
    private TableRenderer $tableRenderer;
    private int $itemsPerPage;
    /** @var callable(string):(string|null)|null */
    private $inputReader;

    /**
     * @param callable(string):(string|null)|null $inputReader
     */
    public function __construct(TableRenderer $tableRenderer, int $itemsPerPage = 25, ?callable $inputReader = null)
    {
        $this->tableRenderer = $tableRenderer;
        $this->itemsPerPage = $itemsPerPage;
        $this->inputReader = $inputReader;
    }

    /**
     * Display query results with interactive paging.
     *
     * @param ConsoleOutput $output
     * @param QueryExecutorInterface $executor
     * @param string $query
     */
    public function display(ConsoleOutput $output, QueryExecutorInterface $executor, string $query): void
    {
        $sqlSection = $output->section();
        $tableSection = $output->section();

        // Show highlighted query
        $sqlSection->writeln('');
        if ($executor instanceof \FQL\Cli\Query\LocalQueryExecutor) {
            $sqlSection->writeln($executor->highlightQuery($query));
        } else {
            $sqlSection->writeln($query);
        }
        $sqlSection->writeln('');

        $tableSection->writeln('Loading...');

        // First execution to get total count
        $result = $executor->execute($query, 1, $this->itemsPerPage);

        if ($result->isEmpty()) {
            $tableSection->clear();
            $sqlSection->clear();
            $tableSection->writeln('<comment>No results found.</comment>');
            return;
        }

        $paginator = new Paginator();
        $paginator->setItemCount($result->totalCount);
        $paginator->setPage(1);
        $paginator->setItemsPerPage($this->itemsPerPage);

        $ctrlFind = '';

        while (true) {
            // Render current page
            if ($paginator->getPage() !== 1 || $result->isEmpty()) {
                $tableSection->clear();
                usleep(50000);
                $tableSection->writeln('Loading...');
                $result = $executor->execute($query, $paginator->getPage(), $this->itemsPerPage);
                $tableSection->clear();
            } else {
                $tableSection->clear();
            }

            if ($paginator->getPageCount() > 1) {
                $this->tableRenderer->render(
                    $tableSection,
                    $result,
                    $paginator->getPage(),
                    $paginator->getPageCount(),
                    $this->itemsPerPage
                );
            } else {
                $this->tableRenderer->renderSinglePage($tableSection, $result);
            }

            if ($ctrlFind !== '') {
                $this->tableRenderer->highlightText($tableSection, $ctrlFind);
            }

            // If single page, no paging controls needed
            if ($paginator->getPageCount() <= 1) {
                break;
            }

            // Paging controls
            $help = 'Press [Enter] or [:n] next, [:b] prev, [:l] last, [:f] first, [/text] search, [:q] quit' . PHP_EOL;
            $input = $this->readPagerInput($help);
            $tableSection->addNewLineOfInputSubmit();
            $tableSection->addNewLineOfInputSubmit();

            $lastPage = $paginator->getLastPage() ?? 1;
            $firstPage = $paginator->getFirstPage();

            switch ($input) {
                case ':q':
                    $tableSection->clear(2);
                    usleep(50000);
                    break 2;
                case ':l':
                    $paginator->setPage($lastPage);
                    break;
                case ':f':
                    $paginator->setPage($firstPage);
                    break;
                case ':b':
                    if ($paginator->getPage() - 1 < 1) {
                        $paginator->setPage($lastPage);
                    } else {
                        $paginator->setPage($paginator->getPage() - 1);
                    }
                    break;
                case ':n':
                case '':
                    if ($paginator->getPage() + 1 > $paginator->getPageCount()) {
                        $paginator->setPage($firstPage);
                    } else {
                        $paginator->setPage($paginator->getPage() + 1);
                    }
                    break;
                default:
                    if (is_string($input) && str_starts_with($input, '/')) {
                        $ctrlFind = trim(substr($input, 1));
                    } elseif (is_numeric($input)) {
                        $pageNum = (int) $input;
                        if ($pageNum > $paginator->getPageCount()) {
                            $paginator->setPage($lastPage);
                        } elseif ($pageNum > 0) {
                            $paginator->setPage($pageNum);
                        } else {
                            $paginator->setPage($firstPage);
                        }
                    }
            }
        }

        usleep(50000);
        $sqlSection->clear();
        $sqlSection->clear(2);
        usleep(50000);
    }

    private function readPagerInput(string $help): string
    {
        if (is_callable($this->inputReader)) {
            $line = ($this->inputReader)($help);
            if ($line === null) {
                return ':q';
            }

            return (string) $line;
        }

        if (function_exists('readline')) {
            $history = function_exists('readline_list_history')
                ? readline_list_history()
                : [];

            if (function_exists('readline_clear_history')) {
                readline_clear_history();
            }

            $line = readline($help);

            if (function_exists('readline_add_history') && is_array($history)) {
                foreach ($history as $entry) {
                    readline_add_history($entry);
                }
            }

            if ($line === false) {
                return ':q';
            }

            return $line;
        }

        fwrite(STDOUT, $help);
        $line = fgets(STDIN);

        if ($line === false) {
            return ':q';
        }

        return rtrim($line, "\r\n");
    }
}
