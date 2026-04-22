<?php

namespace FQL\Cli;

/**
 * One CLI sub-command (`fql-dev lint`, `fql-dev format`, …). Commands receive
 * the argv slice after the command name and two output sinks — stdout for
 * the primary payload (formatted SQL, JSON), stderr for status messages and
 * errors. The returned integer becomes the process exit code:
 * `0` = success, `1` = findings / failure, `2` = usage error.
 */
interface Command
{
    /** Stable identifier matched against argv (`lint`, `format`, `help`). */
    public function name(): string;

    /** One-line description shown by `fql-dev help`. */
    public function description(): string;

    /** Detailed multi-line usage text shown by `fql-dev help <command>`. */
    public function usage(): string;

    /**
     * @param list<string> $argv tokens following the command name
     */
    public function run(array $argv, Output $stdout, Output $stderr): int;
}
