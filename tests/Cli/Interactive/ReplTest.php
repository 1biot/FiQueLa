<?php

namespace Cli\Interactive;

use Client\MockTransport;
use FQL\Cli\Interactive\HistoryManager;
use FQL\Cli\Interactive\Repl;
use FQL\Cli\Interactive\ResultPager;
use FQL\Cli\Query\ApiQueryExecutor;
use FQL\Cli\Query\LocalQueryExecutor;
use FQL\Client\FiQueLaClient;
use FQL\Client\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ReplTest extends TestCase
{
    private string $historyFile;
    private string $tempCsv;

    protected function setUp(): void
    {
        $this->historyFile = sys_get_temp_dir() . '/fql-repl-history-' . uniqid();
        $this->tempCsv = sys_get_temp_dir() . '/fql-repl-data-' . uniqid() . '.csv';
        file_put_contents($this->tempCsv, "id;name\n1;Alice\n2;Bob\n");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->historyFile)) {
            unlink($this->historyFile);
        }
        if (file_exists($this->tempCsv)) {
            unlink($this->tempCsv);
        }
    }

    public function testRunLocalModeExecutesQueryAndExits(): void
    {
        $lines = ['SELECT id, name FROM *;', 'exit'];
        $reader = function (string $prompt) use (&$lines) {
            return array_shift($lines) ?? false;
        };

        $executor = new LocalQueryExecutor($this->tempCsv, 'csv', ';', 'utf-8');
        $history = new HistoryManager($this->historyFile);
        $pager = $this->createMock(ResultPager::class);
        $pager->expects($this->once())->method('display');

        $repl = new Repl(
            new ConsoleOutput(OutputInterface::VERBOSITY_QUIET, false),
            $executor,
            $history,
            $pager,
            $reader
        );

        $code = $repl->run();

        $this->assertEquals(0, $code);
        $this->assertFileExists($this->historyFile);
    }

    public function testRunApiModeSyncsHistoryAtStartAndAfterQuery(): void
    {
        $transport = new MockTransport();
        $client = new FiQueLaClient('https://api.example.com', 'token', $transport);

        // Initial history sync
        $transport->addResponse(new Response(200, [], json_encode([
            ['created_at' => '2024-01-01T10:00:00Z', 'query' => 'SELECT 1', 'runs' => 'SELECT 1'],
        ])));
        // History sync after query execution
        $transport->addResponse(new Response(200, [], json_encode([
            ['created_at' => '2024-01-01T11:00:00Z', 'query' => 'SELECT 2', 'runs' => 'SELECT 2'],
        ])));

        $executor = new ApiQueryExecutor($client, 'test');
        $history = new HistoryManager($this->historyFile);

        $lines = ['SELECT 1;', 'exit'];
        $reader = function (string $prompt) use (&$lines) {
            return array_shift($lines) ?? false;
        };

        $pager = $this->createMock(ResultPager::class);
        $pager->expects($this->once())->method('display');

        $repl = new Repl(
            new ConsoleOutput(OutputInterface::VERBOSITY_QUIET, false),
            $executor,
            $history,
            $pager,
            $reader
        );

        $repl->run();

        $this->assertEquals(2, $transport->getRequestCount());
    }
}
