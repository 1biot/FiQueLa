<?php

namespace FQL\Cli\Output;

use FQL\Cli\Query\QueryResult;
use Symfony\Component\Console\Output\OutputInterface;

class JsonRenderer
{
    /**
     * Render query result as compact JSON to stdout.
     */
    public function render(OutputInterface $output, QueryResult $result): void
    {
        $json = json_encode(
            $result->data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            $output->writeln('[]');
            return;
        }

        $output->writeln($json);
    }
}
