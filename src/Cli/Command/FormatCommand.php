<?php

namespace FQL\Cli\Command;

use FQL\Cli\Args;
use FQL\Cli\Command;
use FQL\Cli\Output;
use FQL\Sql\Formatter\FormatterOptions;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Provider;

/**
 * `fql-dev format` — AST-driven pretty-printer for FQL strings. Accepts the
 * same input modes as `lint` (file / stdin / `-e`) and emits the formatted
 * SQL on stdout. Exits `0` on success, `1` when the source fails to parse
 * (printing the parse error on stderr), `2` on usage / IO errors.
 */
final class FormatCommand implements Command
{
    private const VALUE_LONG = ['indent', 'fields-per-line', 'expression'];

    public function name(): string
    {
        return 'format';
    }

    public function description(): string
    {
        return 'Pretty-print an FQL source string';
    }

    public function usage(): string
    {
        return <<<TXT
        Usage: fql-dev format [options] [<file> | -]
               fql-dev format [options] -e "<sql>"

        Options:
          -e, --expression=SQL        Format the inline SQL string
              --indent=N              Indent width in spaces (default: 4)
              --no-uppercase-keywords Keep keyword casing from the source
              --fields-per-line=N     SELECT field wrap behaviour: 1 = one per line (default), 0 = keep inline
          -h, --help                  Show this help
        TXT;
    }

    public function run(array $argv, Output $stdout, Output $stderr): int
    {
        $args = Args::parse($argv, valueShortFlags: ['e'], valueLongOptions: self::VALUE_LONG);

        [$sql] = $this->resolveInput($args, $stderr);
        if ($sql === null) {
            return 2;
        }

        $options = $this->buildOptions($args, $stderr);
        if ($options === null) {
            return 2;
        }

        try {
            $formatted = Provider::format($sql, $options);
        } catch (ParseException $e) {
            $stderr->writeln($stderr->red('Parse error: ' . $e->getMessage()));
            return 1;
        }

        $stdout->write($formatted);
        if (!str_ends_with($formatted, "\n")) {
            $stdout->writeln();
        }
        return 0;
    }

    /**
     * @return array{0: ?string}
     */
    private function resolveInput(Args $args, Output $stderr): array
    {
        $inline = $args->string('expression') ?? $args->string('e');
        if ($inline !== null) {
            return [$inline];
        }

        $first = $args->first();
        if ($first === '-') {
            return [(string) file_get_contents('php://stdin')];
        }
        if ($first !== null) {
            if (!is_file($first) || !is_readable($first)) {
                $stderr->writeln($stderr->red(sprintf('Cannot read file "%s".', $first)));
                return [null];
            }
            return [(string) file_get_contents($first)];
        }
        if (function_exists('stream_isatty') && @stream_isatty(STDIN)) {
            $stderr->writeln($stderr->red('No input. Pass a file path, `-` for stdin, or `-e "<sql>"`.'));
            return [null];
        }
        return [(string) file_get_contents('php://stdin')];
    }

    private function buildOptions(Args $args, Output $stderr): ?FormatterOptions
    {
        $indent = '    ';
        if ($args->has('indent')) {
            $value = $args->string('indent');
            if ($value === null || !ctype_digit($value) || (int) $value < 0) {
                $stderr->writeln($stderr->red('--indent must be a non-negative integer'));
                return null;
            }
            $indent = str_repeat(' ', (int) $value);
        }

        $uppercase = $args->bool('uppercase-keywords', true);

        $fieldsPerLine = true;
        if ($args->has('fields-per-line')) {
            $value = $args->string('fields-per-line');
            if ($value === null || !ctype_digit($value)) {
                $stderr->writeln($stderr->red('--fields-per-line must be a non-negative integer'));
                return null;
            }
            // Formatter toggles based on bool; emulate the CLI semantic where
            // `--fields-per-line=0` keeps fields inline.
            $fieldsPerLine = (int) $value > 0;
        }

        return new FormatterOptions($indent, $uppercase, $fieldsPerLine);
    }
}
