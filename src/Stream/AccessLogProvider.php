<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Stream\AccessLog\LogFormat;

abstract class AccessLogProvider extends AbstractStream
{
    private string $formatProfile = 'nginx_combined';
    private ?string $customPattern = null;
    private ?string $compiledRegex = null;
    /** @var list<string>|null */
    private ?array $compiledFields = null;
    /** @var array<string, string>|null */
    private ?array $compiledTypes = null;

    protected function __construct(private readonly string $logFilePath)
    {
    }

    public function setFormat(string $format): self
    {
        $this->formatProfile = $format;
        $this->compiledRegex = null;
        $this->compiledFields = null;
        $this->compiledTypes = null;
        return $this;
    }

    public function getFormat(): string
    {
        return $this->formatProfile;
    }

    public function setPattern(string $pattern): self
    {
        $this->customPattern = $pattern;
        $this->compiledRegex = null;
        $this->compiledFields = null;
        $this->compiledTypes = null;
        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->customPattern;
    }

    public function getStream(?string $query): \ArrayIterator
    {
        return new \ArrayIterator(iterator_to_array($this->getStreamGenerator($query)));
    }

    /**
     * @throws Exception\UnableOpenFileException
     * @throws Exception\InvalidFormatException
     */
    public function getStreamGenerator(?string $query): \Generator
    {
        $compiled = $this->compileRegex();
        $regex = $compiled['regex'];
        $fields = $compiled['fields'];
        $types = $compiled['types'];

        $handle = @fopen($this->logFilePath, 'r');
        if ($handle === false) {
            throw new Exception\UnableOpenFileException('Unable to open log file.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");

                if (trim($line) === '') {
                    continue;
                }

                $result = @preg_match($regex, $line, $matches);

                if ($result === false) {
                    $row = LogFormat::nullRow($fields, $types);
                    $row['_raw'] = $line;
                    $row['_error'] = 'regex error: ' . preg_last_error_msg();
                    yield $row;
                    continue;
                }

                if ($result === 0) {
                    $row = LogFormat::nullRow($fields, $types);
                    $row['_raw'] = $line;
                    $row['_error'] = 'pattern mismatch';
                    yield $row;
                    continue;
                }

                $row = LogFormat::normalizeValues($matches, $fields, $types);
                $row['_raw'] = $line;
                $row['_error'] = null;
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    public function provideSource(): string
    {
        $parts = [basename($this->logFilePath)];

        if ($this->formatProfile !== 'nginx_combined') {
            $parts[] = sprintf('"%s"', $this->formatProfile);
        }

        return sprintf('log(%s)', implode(', ', $parts));
    }

    /**
     * @return array{regex: string, fields: list<string>, types: array<string, string>}
     * @throws Exception\InvalidFormatException
     */
    private function compileRegex(): array
    {
        if ($this->compiledRegex !== null && $this->compiledFields !== null && $this->compiledTypes !== null) {
            return [
                'regex' => $this->compiledRegex,
                'fields' => $this->compiledFields,
                'types' => $this->compiledTypes,
            ];
        }

        if ($this->customPattern !== null) {
            $compiled = LogFormat::logFormatToRegex($this->customPattern);
        } else {
            $compiled = LogFormat::resolveProfile($this->formatProfile);
        }

        $this->compiledRegex = $compiled['regex'];
        $this->compiledFields = $compiled['fields'];
        $this->compiledTypes = $compiled['types'];

        return $compiled;
    }
}
