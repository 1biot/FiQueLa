<?php

namespace FQL\Cli\Interactive;

use FQL\Cli\Output\TableRenderer;
use FQL\Cli\Query\ApiQueryExecutor;
use FQL\Cli\Query\LocalQueryExecutor;
use FQL\Cli\Query\QueryExecutorInterface;
use FQL\Cli\Query\QuerySplitter;
use Symfony\Component\Console\Output\ConsoleOutput;

class Repl
{
    private const VERSION = '2.0.0';

    private ConsoleOutput $output;
    private QueryExecutorInterface $executor;
    private HistoryManager $historyManager;
    private ResultPager $resultPager;
    /** @var callable(string):(string|false) */
    private $lineReader;

    public function __construct(
        ConsoleOutput $output,
        QueryExecutorInterface $executor,
        HistoryManager $historyManager,
        ?ResultPager $resultPager = null,
        ?callable $lineReader = null
    ) {
        $this->output = $output;
        $this->executor = $executor;
        $this->historyManager = $historyManager;
        $this->resultPager = $resultPager ?? new ResultPager(new TableRenderer());
        $this->lineReader = $lineReader ?? static fn(string $prompt) => readline($prompt);
    }

    /**
     * Start the interactive REPL loop.
     */
    public function run(): int
    {
        $this->loadHistory();
        $this->printWelcomeMessage();

        $queryBuffer = '';

        while (true) {
            $prompt = empty($queryBuffer) ? 'fql> ' : '  -> ';
            $line = ($this->lineReader)($prompt);

            if ($line === false || strtolower(trim($line)) === 'exit') {
                break;
            }

            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }

            if ($trimmedLine === 'info') {
                $this->printWelcomeMessage();
                continue;
            }

            $queryBuffer .= ' ' . $trimmedLine;

            // Execute when buffer ends with semicolon (outside of quotes)
            if (QuerySplitter::hasTerminatingSemicolon($queryBuffer)) {
                $queryBuffer = QuerySplitter::stripTrailingSemicolon($queryBuffer);
                $this->historyManager->save($queryBuffer);
                $this->executeQuery($queryBuffer);
                $queryBuffer = '';
            }
        }

        return 0;
    }

    private function executeQuery(string $query): void
    {
        // Support multiple queries separated by semicolons (respecting quoted strings)
        $queries = QuerySplitter::split($query);

        foreach ($queries as $key => $singleQuery) {
            try {
                if (count($queries) > 1) {
                    $this->output->writeln('');
                    $this->output->writeln(sprintf('<info>Query #%d:</info>', $key + 1));
                }

                $this->resultPager->display($this->output, $this->executor, $singleQuery);

                if ($this->executor instanceof ApiQueryExecutor) {
                    $this->syncApiHistory();
                }
            } catch (\Exception $e) {
                $this->output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            }
        }
    }

    private function loadHistory(): void
    {
        if ($this->executor instanceof ApiQueryExecutor) {
            $this->syncApiHistory();
        } else {
            $this->historyManager->load();
        }
    }

    private function syncApiHistory(): void
    {
        if (!$this->executor instanceof ApiQueryExecutor) {
            return;
        }

        // For API mode, download history from server
        try {
            $client = $this->executor->getClient();
            $historyEntries = $client->getHistory();

            usort($historyEntries, function ($a, $b): int {
                $aTs = strtotime($a->createdAt) ?: 0;
                $bTs = strtotime($b->createdAt) ?: 0;
                return $aTs <=> $bTs;
            });

            $queries = array_map(fn($entry) => $entry->query, $historyEntries);
            $this->historyManager->replaceWithApiHistory($queries);
        } catch (\Exception) {
            // If history download fails, just load local file
            $this->historyManager->load();
        }
    }

    private function printWelcomeMessage(): void
    {
        $section = $this->output->section();
        $section->writeln(sprintf('FiQueLa CLI v%s', self::VERSION));
        $section->writeln('');

        if ($this->executor instanceof LocalQueryExecutor) {
            $section->writeln(sprintf('<info>Mode:          </info>LOCAL'));
            $section->writeln(sprintf('<info>Memory limit:  </info>%s', ini_get('memory_limit')));

            $file = $this->executor->getFile();
            if ($file !== null && file_exists($file)) {
                $section->writeln(sprintf('<info>File:          </info>%s', $file));
                $section->writeln(sprintf(
                    '<info>File size:     </info>%s',
                    TableRenderer::humanFilesize(filesize($file) ?: 0)
                ));
                $section->writeln(sprintf('<info>Encoding:      </info>%s', $this->executor->getEncoding()));
                $section->writeln(sprintf('<info>Delimiter:     </info>%s', $this->executor->getDelimiter()));
            } elseif ($file !== null) {
                $section->writeln(sprintf('<error>File not found: %s</error>', $file));
            }
        } elseif ($this->executor instanceof ApiQueryExecutor) {
            $section->writeln(sprintf('<info>Mode:          </info>API'));
            $section->writeln(sprintf(
                '<info>Server:        </info>%s (%s)',
                $this->executor->getClient()->getBaseUrl(),
                $this->executor->getServerName()
            ));
        }

        $section->writeln('');
        $section->writeln("Commands end with ;. Type 'exit' or Ctrl+C to quit.");
    }
}
