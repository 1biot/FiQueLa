<?php

namespace FQL\Cli\Command;

use FQL\Cli\Args;
use FQL\Cli\Command;
use FQL\Cli\Output;

/**
 * `fql-dev help [command]` — prints top-level usage with the registered
 * command list, or delegates to a specific command's `usage()` when invoked
 * as `fql-dev help <name>`. Always exits `0` — requesting help is not an
 * error, even when a nonexistent command is named (we fall back to top-level
 * usage with a warning line on stderr).
 */
final class HelpCommand implements Command
{
    /**
     * @param array<string, Command> $commands
     */
    public function __construct(private readonly array $commands = [])
    {
    }

    public function name(): string
    {
        return 'help';
    }

    public function description(): string
    {
        return 'Show help for a command';
    }

    public function usage(): string
    {
        return <<<TXT
        Usage: fql-dev help [command]

        Shows usage information. Without an argument lists all available commands;
        with an argument prints detailed help for that command.
        TXT;
    }

    public function run(array $argv, Output $stdout, Output $stderr): int
    {
        $args = Args::parse($argv);
        $target = $args->first();

        if ($target !== null && isset($this->commands[$target])) {
            $stdout->writeln($this->commands[$target]->usage());
            return 0;
        }

        if ($target !== null) {
            $stderr->writeln($stderr->yellow(sprintf('Unknown command "%s".', $target)));
        }

        $stdout->writeln($stdout->bold('FiQueLa CLI') . ' — SQL-like querying for structured files');
        $stdout->writeln('');
        $stdout->writeln('Usage: fql-dev [global options] <command> [options] [arguments]');
        $stdout->writeln('');
        $stdout->writeln('Global options:');
        $stdout->writeln('  -h, --help       Show this help');
        $stdout->writeln('  -V, --version    Print the installed FiQueLa version');
        $stdout->writeln('      --color      Force ANSI colors in output');
        $stdout->writeln('      --no-color   Disable ANSI colors even on a TTY');
        $stdout->writeln('');
        $stdout->writeln('Available commands:');
        foreach ($this->commands as $cmd) {
            $stdout->writeln(sprintf('  %-12s %s', $stdout->cyan($cmd->name()), $cmd->description()));
        }
        $stdout->writeln('');
        $stdout->writeln('Run `fql-dev help <command>` for detailed usage.');

        return 0;
    }
}
