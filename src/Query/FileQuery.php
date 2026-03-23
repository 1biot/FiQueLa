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
     */
    private static function toString(
        ?string $format,
        ?string $file,
        array $params,
        ?string $query
    ): string {
        $result = '';

        if ($format !== null && $file !== null) {
            try {
                $extension = Enum\Format::fromExtension($format);
                $defaults = $extension->getDefaultParams();
            } catch (Exception\InvalidFormatException) {
                $defaults = [];
            }

            // Determine which params are non-default
            $nonDefault = array_filter(
                $params,
                fn ($v, $k) => !isset($defaults[$k]) || $defaults[$k] !== $v,
                ARRAY_FILTER_USE_BOTH
            );

            $parts = [$file];

            if ($nonDefault !== []) {
                // For positional output, we need to include all params up to the last non-default
                $keys = array_keys($defaults);
                $lastNonDefaultIndex = -1;
                foreach ($keys as $i => $key) {
                    if (isset($nonDefault[$key])) {
                        $lastNonDefaultIndex = $i;
                    }
                }

                for ($i = 0; $i <= $lastNonDefaultIndex; $i++) {
                    $key = $keys[$i];
                    $value = $params[$key] ?? $defaults[$key] ?? '';
                    $parts[] = sprintf('"%s"', $value);
                }
            }

            $result = $format . '(' . implode(', ', $parts) . ')';
        } elseif ($format !== null) {
            $result = $format . '()';
        }

        if ($query !== null) {
            if ($file !== null) {
                $result .= '.';
            }
            $result .= $query;
        }

        return $result;
    }
}
