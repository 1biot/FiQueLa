<?php

namespace FQL\Cli;

/**
 * Thin output sink with optional ANSI color handling. Colors are enabled when
 * the underlying stream is a TTY (`stream_isatty`), unless explicitly forced
 * on/off via the constructor (`--color` / `--no-color`). All `red()`/`cyan()`
 * helpers become no-ops in plain mode, so the same command implementation
 * works transparently against both terminals and redirected files / CI logs.
 */
final class Output
{
    public const RESET = "\033[0m";
    public const BOLD = "\033[1m";
    public const DIM = "\033[2m";
    public const RED = "\033[31m";
    public const GREEN = "\033[32m";
    public const YELLOW = "\033[33m";
    public const BLUE = "\033[34m";
    public const MAGENTA = "\033[35m";
    public const CYAN = "\033[36m";

    /**
     * @param resource $stream
     */
    public function __construct(
        public readonly bool $useColor,
        private $stream,
    ) {
    }

    /**
     * Constructor helper for stdout with TTY auto-detection. Pass `true`/
     * `false` in `$override` to mirror `--color`/`--no-color` CLI flags.
     */
    public static function forStdout(?bool $override = null): self
    {
        return new self(self::resolveColor(STDOUT, $override), STDOUT);
    }

    public static function forStderr(?bool $override = null): self
    {
        return new self(self::resolveColor(STDERR, $override), STDERR);
    }

    /**
     * Constructor helper for tests — hands the caller a memory stream so
     * `fetch()` can read back whatever was written. Colors default to off.
     *
     * @return array{0: Output, 1: resource}
     */
    public static function memory(bool $useColor = false): array
    {
        $stream = fopen('php://memory', 'w+');
        \assert($stream !== false);
        return [new self($useColor, $stream), $stream];
    }

    public function write(string $text): void
    {
        fwrite($this->stream, $text);
    }

    public function writeln(string $text = ''): void
    {
        fwrite($this->stream, $text . PHP_EOL);
    }

    public function red(string $s): string
    {
        return $this->wrap($s, self::RED);
    }

    public function green(string $s): string
    {
        return $this->wrap($s, self::GREEN);
    }

    public function yellow(string $s): string
    {
        return $this->wrap($s, self::YELLOW);
    }

    public function cyan(string $s): string
    {
        return $this->wrap($s, self::CYAN);
    }

    public function magenta(string $s): string
    {
        return $this->wrap($s, self::MAGENTA);
    }

    public function bold(string $s): string
    {
        return $this->wrap($s, self::BOLD);
    }

    public function dim(string $s): string
    {
        return $this->wrap($s, self::DIM);
    }

    private function wrap(string $s, string $code): string
    {
        return $this->useColor ? $code . $s . self::RESET : $s;
    }

    /**
     * @param resource $stream
     */
    private static function resolveColor($stream, ?bool $override): bool
    {
        if ($override !== null) {
            return $override;
        }
        // function_exists guard keeps the class usable on exotic SAPIs where
        // `stream_isatty` may be missing.
        if (!function_exists('stream_isatty')) {
            return false;
        }
        return @stream_isatty($stream);
    }
}
