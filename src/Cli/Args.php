<?php

namespace FQL\Cli;

/**
 * Minimal argv parser that intentionally avoids pulling in symfony/console or
 * similar. Recognises:
 *
 *  - Long flags: `--flag`            → ['flag' => true]
 *  - Negated long flags: `--no-flag` → ['flag' => false]
 *  - Long options: `--opt=value`     → ['opt' => 'value']
 *  - Long options split: `--opt value` → ['opt' => 'value']
 *  - Short options: `-e value`       → ['e' => 'value']
 *  - Short flags: `-h`               → ['h' => true]
 *  - Bare `-`                        → kept as positional (stdin sentinel)
 *  - Terminator `--`                 → everything after is forced positional
 *
 * Whether a short/long form takes a value is decided by the caller at lookup
 * time (`bool()` vs `string()`). Parser only separates options from values
 * using `=`, short-form lookahead, and the caller's expectations.
 */
final readonly class Args
{
    /**
     * @param list<string>                 $positional arguments after the command, in order
     * @param array<string, bool|string>   $options    map of long/short name → value
     */
    public function __construct(
        public array $positional,
        public array $options,
    ) {
    }

    /**
     * Parses a list of argv tokens into positional + options.
     *
     * Long options that take a value (`--severity error`) MUST be listed in
     * `$valueLongOptions` — otherwise `--severity` is treated as a bare
     * boolean flag and `error` falls through as a positional. The same holds
     * for short options via `$valueShortFlags`. Explicit `--opt=value` syntax
     * always works regardless of the list. This keeps parsing deterministic
     * without pre-declaring the entire schema upfront.
     *
     * @param list<string> $argv             raw tokens already stripped of the script / command name
     * @param list<string> $valueShortFlags  short flags (without the dash) that take a value
     * @param list<string> $valueLongOptions long option names (without `--`) that take a value
     */
    public static function parse(array $argv, array $valueShortFlags = ['e'], array $valueLongOptions = []): self
    {
        $positional = [];
        $options = [];
        $forcePositional = false;
        $count = count($argv);

        for ($i = 0; $i < $count; $i++) {
            $token = $argv[$i];

            if ($forcePositional) {
                $positional[] = $token;
                continue;
            }

            if ($token === '--') {
                $forcePositional = true;
                continue;
            }

            if ($token === '-' || $token === '') {
                // Bare `-` is a stdin sentinel; empty string shouldn't happen
                // in practice but shouldn't break parsing either.
                $positional[] = $token;
                continue;
            }

            // --long / --long=value / --no-long
            if (str_starts_with($token, '--')) {
                $body = substr($token, 2);
                if ($body === '') {
                    // Lone `--` is handled above; a stray `--` with nothing
                    // following after stripping prefix means malformed input.
                    continue;
                }
                $eq = strpos($body, '=');
                if ($eq !== false) {
                    $name = substr($body, 0, $eq);
                    $value = substr($body, $eq + 1);
                    $options[$name] = $value;
                    continue;
                }
                // --no-flag → flag=false
                if (str_starts_with($body, 'no-')) {
                    $options[substr($body, 3)] = false;
                    continue;
                }
                // --opt value — only if `opt` is registered as value-taking.
                if (in_array($body, $valueLongOptions, true) && $i + 1 < $count) {
                    $options[$body] = $argv[++$i];
                    continue;
                }
                $options[$body] = true;
                continue;
            }

            // -x / -xvalue / -x value
            if (str_starts_with($token, '-') && strlen($token) >= 2) {
                $short = substr($token, 1, 1);
                $rest = substr($token, 2);
                if ($rest !== '') {
                    // -e"SELECT ..." or -eFOO — treat rest as the value.
                    $options[$short] = $rest;
                    continue;
                }
                if (in_array($short, $valueShortFlags, true) && $i + 1 < $count) {
                    $options[$short] = $argv[++$i];
                    continue;
                }
                $options[$short] = true;
                continue;
            }

            $positional[] = $token;
        }

        return new self($positional, $options);
    }

    /**
     * Boolean lookup. Present flag without explicit value → `true`. Explicit
     * string value "0"/"false"/"off" → `false` (for config-driven scenarios).
     * Any other string → `true`.
     */
    public function bool(string $name, bool $default = false): bool
    {
        if (!array_key_exists($name, $this->options)) {
            return $default;
        }
        $value = $this->options[$name];
        if (is_bool($value)) {
            return $value;
        }
        return !in_array(strtolower($value), ['0', 'false', 'off', 'no'], true);
    }

    /**
     * String option lookup. Returns `$default` if option is missing or is a
     * bare boolean flag (caller asked for a value but flag was passed without
     * one).
     */
    public function string(string $name, ?string $default = null): ?string
    {
        if (!array_key_exists($name, $this->options)) {
            return $default;
        }
        $value = $this->options[$name];
        if (is_bool($value)) {
            return $default;
        }
        return $value;
    }

    /**
     * Checks whether an option was present on the command line at all
     * (regardless of whether the caller asked for bool or string).
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    public function first(): ?string
    {
        return $this->positional[0] ?? null;
    }
}
