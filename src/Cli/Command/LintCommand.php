<?php

namespace FQL\Cli\Command;

use FQL\Cli\Args;
use FQL\Cli\Command;
use FQL\Cli\Output;
use FQL\Sql\Lint\LintIssue;
use FQL\Sql\Lint\LintReport;
use FQL\Sql\Lint\Severity;
use FQL\Sql\Provider;

/**
 * `fql-dev lint` — static analysis of an FQL source. Accepts a file path,
 * stdin (via `-` or no argument when piped), or an inline string (`-e`).
 * Delegates the actual analysis to {@see Provider::lint()} and renders
 * findings in one of three formats (human / plain / json). Exits `1` when
 * any ERROR-severity issue survives severity filtering, otherwise `0`;
 * usage / IO errors exit `2`.
 */
final class LintCommand implements Command
{
    private const VALUE_LONG = ['severity', 'format', 'expression'];

    public function name(): string
    {
        return 'lint';
    }

    public function description(): string
    {
        return 'Static analysis of an FQL source file or string';
    }

    public function usage(): string
    {
        return <<<TXT
        Usage: fql-dev lint [options] [<file> | -]
               fql-dev lint [options] -e "<sql>"

        Options:
          -e, --expression=SQL     Lint the inline SQL string (instead of a file)
              --check-fs           Verify every FROM <format>(path) source exists and is readable
              --severity=LEVEL     Show issues at LEVEL or higher (error|warning|info, default: info)
              --format=FORMAT      Output format: human (default) | plain | json
          -h, --help               Show this help

        Exit codes:
          0  no errors (warnings/info allowed)
          1  one or more error-level issues
          2  usage or IO error
        TXT;
    }

    public function run(array $argv, Output $stdout, Output $stderr): int
    {
        $args = Args::parse($argv, valueShortFlags: ['e'], valueLongOptions: self::VALUE_LONG);

        [$sql, $label] = $this->resolveInput($args, $stderr);
        if ($sql === null) {
            return 2;
        }

        $minSeverity = $this->resolveSeverity($args, $stderr);
        if ($minSeverity === null) {
            return 2;
        }

        $format = $args->string('format', 'human') ?? 'human';
        if (!in_array($format, ['human', 'plain', 'json'], true)) {
            $stderr->writeln($stderr->red(sprintf('Unknown --format value "%s". Use human, plain or json.', $format)));
            return 2;
        }

        $report = Provider::lint($sql, $args->bool('check-fs', false));
        $filtered = $report->filterBySeverity($minSeverity);

        $this->render($filtered, $format, $label, $stdout, $stderr);

        return $filtered->hasErrors() ? 1 : 0;
    }

    /**
     * @return array{0: ?string, 1: string}   [source SQL or null on error, display label]
     */
    private function resolveInput(Args $args, Output $stderr): array
    {
        $inline = $args->string('expression') ?? $args->string('e');
        if ($inline !== null) {
            return [$inline, '<inline>'];
        }

        $first = $args->first();
        if ($first === '-') {
            return [$this->readStdin(), '<stdin>'];
        }
        if ($first !== null) {
            if (!is_file($first) || !is_readable($first)) {
                $stderr->writeln($stderr->red(sprintf('Cannot read file "%s".', $first)));
                return [null, $first];
            }
            $content = file_get_contents($first);
            if ($content === false) {
                $stderr->writeln($stderr->red(sprintf('Failed to read file "%s".', $first)));
                return [null, $first];
            }
            return [$content, $first];
        }

        // No arg → check if stdin has data piped in. When there's a real TTY
        // attached we interpret "no args" as usage error; on a non-TTY (pipe)
        // we read stdin.
        if (function_exists('stream_isatty') && @stream_isatty(STDIN)) {
            $stderr->writeln($stderr->red('No input. Pass a file path, `-` for stdin, or `-e "<sql>"`.'));
            $stderr->writeln('');
            $stderr->writeln($this->usage());
            return [null, ''];
        }
        return [$this->readStdin(), '<stdin>'];
    }

    private function readStdin(): string
    {
        $buf = file_get_contents('php://stdin');
        return $buf === false ? '' : $buf;
    }

    private function resolveSeverity(Args $args, Output $stderr): ?Severity
    {
        $value = $args->string('severity', 'info') ?? 'info';
        $severity = Severity::tryFrom(strtolower($value));
        if ($severity === null) {
            $stderr->writeln($stderr->red(sprintf('Unknown --severity value "%s". Use error, warning or info.', $value)));
            return null;
        }
        return $severity;
    }

    private function render(LintReport $report, string $format, string $label, Output $stdout, Output $stderr): void
    {
        if ($format === 'json') {
            $stdout->writeln((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }

        $useColor = $format === 'human' && $stdout->useColor;
        $renderer = new Output($useColor, STDOUT);
        foreach ($report as $issue) {
            $stdout->writeln($this->formatIssue($issue, $label, $renderer));
        }

        $errorCount = 0;
        $warningCount = 0;
        $infoCount = 0;
        foreach ($report as $issue) {
            match ($issue->severity) {
                Severity::ERROR => $errorCount++,
                Severity::WARNING => $warningCount++,
                Severity::INFO => $infoCount++,
            };
        }
        $stderr->writeln(sprintf(
            '%d error%s, %d warning%s, %d info.',
            $errorCount,
            $errorCount === 1 ? '' : 's',
            $warningCount,
            $warningCount === 1 ? '' : 's',
            $infoCount
        ));
    }

    private function formatIssue(LintIssue $issue, string $label, Output $color): string
    {
        $line = $issue->position?->line ?? 0;
        $col = $issue->position?->column ?? 0;
        $location = $line > 0 ? sprintf('%s:%d:%d', $label, $line, $col) : $label;

        $severityText = match ($issue->severity) {
            Severity::ERROR => $color->red('error'),
            Severity::WARNING => $color->yellow('warning'),
            Severity::INFO => $color->cyan('info'),
        };

        return sprintf(
            '%s: %s  %s  %s',
            $location,
            $severityText,
            $color->dim($issue->rule),
            $issue->message
        );
    }
}
