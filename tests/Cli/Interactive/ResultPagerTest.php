<?php

namespace Cli\Interactive;

use Cli\Interactive\Support\FakePagedExecutor;
use FQL\Cli\Interactive\ResultPager;
use FQL\Cli\Output\TableRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ResultPagerTest extends TestCase
{
    public function testDisplaySinglePageWithoutInteractiveInput(): void
    {
        $executor = new FakePagedExecutor([
            1 => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ], 2);

        $pager = new ResultPager(new TableRenderer(50), 25, static fn(string $help): ?string => ':q');
        $output = new ConsoleOutput(OutputInterface::VERBOSITY_QUIET, false);

        $pager->display($output, $executor, 'SELECT id, name FROM *');

        $this->assertTrue(true);
    }

    public function testDisplayMultiPageWithNavigationCommands(): void
    {
        $inputs = [':n', ':b', ':l', ':f', '/Alice', '2', ':q'];
        $reader = function (string $help) use (&$inputs): ?string {
            return array_shift($inputs) ?? ':q';
        };

        $executor = new FakePagedExecutor([
            1 => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
            2 => [
                ['id' => 3, 'name' => 'Carol'],
                ['id' => 4, 'name' => 'David'],
            ],
        ], 4);

        $pager = new ResultPager(new TableRenderer(50), 2, $reader);
        $output = new ConsoleOutput(OutputInterface::VERBOSITY_QUIET, false);

        $pager->display($output, $executor, 'SELECT id, name FROM *');

        $this->assertTrue(true);
    }
}
