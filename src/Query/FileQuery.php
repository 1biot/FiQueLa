<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exception;
use FQL\Interface;

final class FileQuery implements \Stringable
{
    // @codingStandardsIgnoreStart
    private const REGEXP = '(?<fq>(?<fs>(?<t>[a-zA-Z]{2,8})\((?<p>[\w\s.\-\/]+(?:\.\w{2,5})?)(?<a>(?:,\s*(?:\w+\s*:\s*"[^"]*"|"[^"]*"))*)\))?(?<q>\*|\.*[\w*.\-\_]{1,})?)';
    // @codingStandardsIgnoreEnd

    public readonly ?string $format;
    public readonly ?Enum\Format $extension;
    public readonly ?string $file;
    /** @var array<string, mixed> */
    public readonly array $params;
    public readonly ?string $query;

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function __construct(private readonly string $queryPath)
    {
        if (!preg_match('/^' . self::getRegexp() . '$/u', $this->queryPath, $matches)) {
            throw new Exception\FileQueryException(sprintf('Invalid query path "%s"', $this->queryPath));
        }

        $format = $matches['t'] ?? null;
        $this->format = ($format !== '' && $format !== null ? strtolower($format) : null);

        $file = $matches['p'] ?? null;
        $this->file = ($file !== '' ? $file : null);

        if ($this->format !== null) {
            $this->extension = Enum\Format::fromExtension($this->format);

            $argsString = ltrim($matches['a'] ?? '', ', ');
            ['positional' => $positional, 'named' => $named] = $this->parseArguments($argsString);

            $this->params = $this->extension->normalizeParams($positional, $named);
            $this->extension->validateParams($this->params);
        } else {
            $this->extension = null;
            $this->params = [];
        }

        $query = $matches['q'] ?? null;
        if ($query !== null && $query !== '') {
            $trimmed = ltrim($query, '.');
            $this->query = $trimmed !== '' ? $trimmed : null;
        } else {
            $this->query = null;
        }
    }

    /**
     * @return mixed
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withFile(?string $file): self
    {
        return new self(self::toString($this->format, $file, $this->params, $this->query));
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withParam(string $key, string $value): self
    {
        $params = array_merge($this->params, [$key => $value]);
        return new self(self::toString($this->format, $this->file, $params, $this->query));
    }

    /**
     * Copy with the given parameter set. Keys the format does not know are ignored when
     * writing; missing keys fall back to the format defaults.
     *
     * @param array<string, string|int|bool> $params
     * @throws Exception\FileQueryException when a value cannot be written (contains `"`)
     * @throws Exception\InvalidFormatException
     */
    public function withParams(array $params): self
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            $normalized[$key] = self::paramValueToString($value);
        }
        return new self(self::toString($this->format, $this->file, $normalized, $this->query, true));
    }

    /**
     * Builds a file query from its parts, e.g.
     * `FileQuery::fromParts('csv', './data.csv', ['delimiter' => ';'], 'items')`
     * is `csv(./data.csv, delimiter: ";").items` (or the positional form, see {@see toFql()}).
     *
     * @param array<string, string|int|bool> $params
     * @throws Exception\FileQueryException when a part cannot be written
     * @throws Exception\InvalidFormatException
     */
    public static function fromParts(
        ?string $format,
        ?string $file,
        array $params = [],
        ?string $query = null,
        bool $namedParams = false
    ): self {
        $normalized = [];
        foreach ($params as $key => $value) {
            $normalized[$key] = self::paramValueToString($value);
        }
        return new self(self::toString($format, $file, $normalized, $query, $namedParams));
    }

    /**
     * Writes the file query as FQL text. Only parameters that differ from the format
     * defaults are written. With `$namedParams = false` they are positional when every
     * one of them has a position (`csv(a.csv, "utf-8", ";")`), otherwise — and always with
     * `$namedParams = true` — named (`csv(a.csv, delimiter: ";", enclosure: "'")`).
     *
     * @throws Exception\FileQueryException when a part cannot be written as FQL
     */
    public function toFql(bool $namedParams = false): string
    {
        return self::toString($this->format, $this->file, $this->params, $this->query, $namedParams);
    }

    /**
     * @deprecated Use withParam('encoding', $encoding) instead
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withEncoding(string $encoding): self
    {
        return $this->withParam('encoding', $encoding);
    }

    /**
     * @deprecated Use withParam('delimiter', $delimiter) instead
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withDelimiter(string $delimiter): self
    {
        return $this->withParam('delimiter', $delimiter);
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withQuery(?string $query): self
    {
        return new self(self::toString($this->format, $this->file, $this->params, $query));
    }

    /**
     * @throws Exception\FileQueryException
     * @throws Exception\InvalidFormatException
     */
    public function withFormat(?string $format): self
    {
        return new self(self::toString($format, $this->file, $this->params, $this->query));
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public static function fromStream(Interface\Stream $stream): self
    {
        return new self($stream->provideSource());
    }

    public static function getRegexp(): string
    {
        return self::REGEXP;
    }

    public function __toString(): string
    {
        return self::toString($this->format, $this->file, $this->params, $this->query);
    }

    /**
     * @return array{positional: array<int, string>, named: array<string, string>}
     * @throws Exception\InvalidFormatException
     */
    private function parseArguments(string $argsString): array
    {
        if (trim($argsString) === '') {
            return ['positional' => [], 'named' => []];
        }

        $args = $this->splitArguments($argsString);
        $positional = [];
        $named = [];
        $hasNamed = false;

        foreach ($args as $arg) {
            $arg = trim($arg);

            if (preg_match('/^(\w+)\s*:\s*"([^"]*)"$/', $arg, $matches)) {
                if ($positional !== []) {
                    throw new Exception\InvalidFormatException(
                        'Cannot mix positional and named parameters'
                    );
                }
                $hasNamed = true;
                $named[$matches[1]] = $matches[2];
            } elseif (preg_match('/^"([^"]*)"$/', $arg, $matches)) {
                if ($hasNamed) {
                    throw new Exception\InvalidFormatException(
                        'Cannot mix positional and named parameters'
                    );
                }
                $positional[] = $matches[1];
            } else {
                throw new Exception\InvalidFormatException(
                    sprintf('Parameter values must be quoted: %s', $arg)
                );
            }
        }

        return ['positional' => $positional, 'named' => $named];
    }

    /**
     * @return string[]
     */
    private function splitArguments(string $argsString): array
    {
        $args = [];
        $current = '';
        $inQuotes = false;

        for ($i = 0, $len = strlen($argsString); $i < $len; $i++) {
            $char = $argsString[$i];

            if (!$inQuotes && $char === '"') {
                $inQuotes = true;
                $current .= $char;
            } elseif ($inQuotes && $char === '"') {
                $inQuotes = false;
                $current .= $char;
            } elseif (!$inQuotes && $char === ',') {
                if (trim($current) !== '') {
                    $args[] = trim($current);
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $args[] = trim($current);
        }

        return $args;
    }

    /**
     * @param array<string, mixed> $params
     * @throws Exception\FileQueryException when a part cannot be written as FQL
     */
    private static function toString(
        ?string $format,
        ?string $file,
        array $params,
        ?string $query,
        bool $namedParams = false
    ): string {
        $result = '';

        if ($format !== null) {
            if (preg_match('/^[a-zA-Z]{2,8}$/', $format) !== 1) {
                throw new Exception\FileQueryException(
                    sprintf('Format "%s" cannot be written in a file query: expected 2-8 letters', $format)
                );
            }
            if ($file === null || $file === '') {
                throw new Exception\FileQueryException(
                    sprintf('A file query with format "%s" needs a file path', $format)
                );
            }
            if (preg_match('/^[\w\s.\-\/]+$/u', $file) !== 1) {
                throw new Exception\FileQueryException(sprintf(
                    'File path "%s" cannot be written in a file query: only letters, digits, whitespace,'
                    . ' "_", ".", "-" and "/" are allowed',
                    $file
                ));
            }

            try {
                $extension = Enum\Format::fromExtension($format);
                $defaults = $extension->getDefaultParams();
                $known = $extension->getParamNames();
                $positional = $extension->getPositionalParamNames();
            } catch (Exception\InvalidFormatException) {
                $defaults = [];
                $known = [];
                $positional = [];
            }

            // Only parameters the format understands and that differ from its defaults,
            // in the format's own order.
            $nonDefault = [];
            foreach ($known as $key) {
                if (!array_key_exists($key, $params) || $params[$key] === null) {
                    continue;
                }
                $value = self::paramValueToString($params[$key]);
                if (isset($defaults[$key]) && (string) $defaults[$key] === $value) {
                    continue;
                }
                if (str_contains($value, '"')) {
                    throw new Exception\FileQueryException(sprintf(
                        'Parameter "%s" cannot be written in a file query: the value %s contains a double quote',
                        $key,
                        json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    ));
                }
                $nonDefault[$key] = $value;
            }

            $parts = [$file];
            $canBePositional = array_diff(array_keys($nonDefault), $positional) === [];
            if ($nonDefault !== [] && ($namedParams || !$canBePositional)) {
                foreach ($nonDefault as $key => $value) {
                    $parts[] = sprintf('%s: "%s"', $key, $value);
                }
            } elseif ($nonDefault !== []) {
                // Positional output includes every position up to the last non-default one.
                $lastNonDefaultIndex = -1;
                foreach ($positional as $i => $key) {
                    if (isset($nonDefault[$key])) {
                        $lastNonDefaultIndex = $i;
                    }
                }

                for ($i = 0; $i <= $lastNonDefaultIndex; $i++) {
                    $key = $positional[$i];
                    $parts[] = sprintf('"%s"', $nonDefault[$key] ?? (string) ($defaults[$key] ?? ''));
                }
            }

            $result = $format . '(' . implode(', ', $parts) . ')';
        }

        if ($query !== null) {
            if (preg_match('/^(?:\*|[\w*.\-]+)$/u', $query) !== 1) {
                throw new Exception\FileQueryException(sprintf(
                    'Path in file "%s" cannot be written in a file query: only letters, digits,'
                    . ' "_", "-", "." and "*" are allowed',
                    $query
                ));
            }
            if ($file !== null) {
                $result .= '.';
            }
            $result .= $query;
        }

        return $result;
    }

    private static function paramValueToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return is_scalar($value) ? (string) $value : '';
    }
}
