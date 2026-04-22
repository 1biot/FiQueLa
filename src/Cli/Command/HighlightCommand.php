<?php

namespace FQL\Cli\Command;

use FQL\Cli\Args;
use FQL\Cli\Command;
use FQL\Cli\Output;
use FQL\Sql\Highlighter\HighlighterKind;
use FQL\Sql\Provider;

/**
 * `fql-dev highlight` — renders FQL source with syntax highlighting. Uses
 * the same AST-driven highlighter that powers `Sql\Provider::highlight()`.
 * Output format is `bash` (ANSI escape codes, default) or `html` (wrapped
 * `<span>`s).
 */
final class HighlightCommand implements Command
{
    private const VALUE_LONG = ['format', 'expression'];

    public function name(): string
    {
        return 'highlight';
    }

    public function description(): string
    {
        return 'Render FQL source with syntax highlighting';
    }

    public function usage(): string
    {
        return <<<TXT
        Usage: fql-dev highlight [options] [<file> | -]
               fql-dev highlight [options] -e "<sql>"

        Options:
          -e, --expression=SQL     Highlight the inline SQL string
              --format=KIND        Output format: bash (ANSI, default) | html
          -h, --help               Show this help
        TXT;
    }

    public function run(array $argv, Output $stdout, Output $stderr): int
    {
        $args = Args::parse($argv, valueShortFlags: ['e'], valueLongOptions: self::VALUE_LONG);

        [$sql] = $this->resolveInput($args, $stderr);
        if ($sql === null) {
            return 2;
        }

        $format = $args->string('format', 'bash') ?? 'bash';
        $kind = match ($format) {
            'bash', 'ansi' => HighlighterKind::BASH,
            'html' => HighlighterKind::HTML,
            default => null,
        };
        if ($kind === null) {
            $stderr->writeln($stderr->red(sprintf('Unknown --format value "%s". Use bash or html.', $format)));
            return 2;
        }

        $highlighted = Provider::highlight($sql, $kind);
        $stdout->write($highlighted);
        if (!str_ends_with($highlighted, "\n")) {
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
}
