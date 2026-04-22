<?php

namespace FQL\Cli;

use FQL\Cli\Command\FormatCommand;
use FQL\Cli\Command\HelpCommand;
use FQL\Cli\Command\HighlightCommand;
use FQL\Cli\Command\LintCommand;

/**
 * CLI entry point invoked by `bin/fql-dev`. Parses a very small set of global
 * flags (`--help`, `--version`, `--color`, `--no-color`) itself and then
 * forwards the rest of argv to the resolved subcommand. Subcommands own
 * their own Args parsing — keeps per-command schemas local and avoids a
 * single monolithic options table.
 */
final class Application
{
    public const VERSION = '3.0.0-dev';

    /** @var array<string, Command> */
    private array $commands;

    /**
     * @param array<string, Command>|null $commands inject to override defaults (tests)
     */
    public function __construct(?array $commands = null)
    {
        $this->commands = $commands ?? $this->defaultCommands();
    }

    /**
     * @return array<string, Command>
     */
    private function defaultCommands(): array
    {
        $cmds = [
            'lint' => new LintCommand(),
            'format' => new FormatCommand(),
            'highlight' => new HighlightCommand(),
        ];
        // HelpCommand needs visibility into the other commands for the
        // `help <command>` detail view — injected after the rest are built.
        $cmds['help'] = new HelpCommand($cmds);
        return $cmds;
    }

    /**
     * @param list<string> $argv complete argv including the script name at index 0
     */
    public function run(array $argv, ?Output $stdoutOverride = null, ?Output $stderrOverride = null): int
    {
        array_shift($argv);   // drop script path
        [$globalArgv, $commandArgv] = $this->splitGlobalArgs($argv);
        $globals = Args::parse($globalArgv);

        $colorOverride = null;
        if ($globals->has('color')) {
            $colorOverride = $globals->bool('color');
        } elseif ($globals->has('no-color')) {
            $colorOverride = false;
        }

        $stdout = $stdoutOverride ?? Output::forStdout($colorOverride);
        $stderr = $stderrOverride ?? Output::forStderr($colorOverride);

        if ($globals->bool('version') || $globals->bool('V')) {
            $stdout->writeln('fql-dev ' . self::VERSION);
            return 0;
        }

        $commandName = $commandArgv[0] ?? null;

        // `fql-dev --help` or bare `fql-dev` → top-level help.
        if ($commandName === null || $globals->bool('help') || $globals->bool('h')) {
            if ($commandName !== null && isset($this->commands[$commandName])) {
                // `fql-dev lint --help` → delegate to command's help.
                $stdout->writeln($this->commands[$commandName]->usage());
                return 0;
            }
            return $this->commands['help']->run([], $stdout, $stderr);
        }

        if (!isset($this->commands[$commandName])) {
            $stderr->writeln($stderr->red(sprintf('Unknown command "%s".', $commandName)));
            $stderr->writeln('');
            $this->commands['help']->run([], $stderr, $stderr);
            return 2;
        }

        // `fql-dev <cmd> --help` shortcut
        $rest = array_slice($commandArgv, 1);
        if (in_array('--help', $rest, true) || in_array('-h', $rest, true)) {
            $stdout->writeln($this->commands[$commandName]->usage());
            return 0;
        }

        return $this->commands[$commandName]->run($rest, $stdout, $stderr);
    }

    /**
     * Splits argv into (global-only options, command + its argv). Global
     * options live before the first non-option token (= the command name).
     * Anything after the command name is forwarded verbatim.
     *
     * @param list<string> $argv
     * @return array{0: list<string>, 1: list<string>}
     */
    private function splitGlobalArgs(array $argv): array
    {
        $globals = [];
        $rest = [];
        $commandFound = false;
        $globalValueOptions = [];   // no global options take values today

        foreach ($argv as $token) {
            if ($commandFound) {
                $rest[] = $token;
                continue;
            }
            if (str_starts_with($token, '-') && $token !== '-') {
                $globals[] = $token;
                continue;
            }
            $commandFound = true;
            $rest[] = $token;
        }

        return [$globals, $rest];
    }
}
