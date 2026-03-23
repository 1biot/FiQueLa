<?php

namespace FQL\Enum;

use FQL\Exception;
use FQL\Interface;
use FQL\Stream;

enum Format: string
{
    case XML = 'xml';
    case JSON = 'json';
    case JSON_STREAM = 'jsonFile';
    case ND_JSON = 'ndJson';
    case CSV = 'csv';
    case YAML = 'yaml';
    case NEON = 'neon';
    case XLS = 'xls';
    case ODS = 'ods';
    case DIR = 'dir';

    /**
     * @return class-string<Stream\Csv|Stream\Json|Stream\JsonStream|Stream\Xml|Stream\Neon|Stream\Yaml|Stream\NDJson|Stream\Xls|Stream\Ods|Stream\Dir>
     */
    public function getFormatProviderClass(): string
    {
        return match ($this) {
            self::XML => Stream\Xml::class,
            self::JSON => Stream\Json::class,
            self::JSON_STREAM => Stream\JsonStream::class,
            self::ND_JSON => Stream\NDJson::class,
            self::CSV => Stream\Csv::class,
            self::YAML => Stream\Yaml::class,
            self::NEON => Stream\Neon::class,
            self::XLS => Stream\Xls::class,
            self::ODS => Stream\Ods::class,
            self::DIR => Stream\Dir::class,
        };
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public static function fromExtension(string $extension): self
    {
        return match (strtolower($extension)) {
            'xml' => self::XML,
            'json', 'jsonfile' => self::JSON_STREAM,
            'ndjson' => self::ND_JSON,
            'csv', 'tsv' => self::CSV,
            'yaml', 'yml' => self::YAML,
            'neon' => self::NEON,
            'xls', 'xlsx' => self::XLS,
            'ods' => self::ODS,
            'dir' => self::DIR,
            default => throw new Exception\InvalidFormatException(sprintf('Unsupported file format "%s"', $extension)),
        };
    }

    /**
     * @implements Interface\Stream<Stream\Xml|Stream\Json|Stream\JsonStream|Stream\Yaml|Stream\Neon|Stream\Csv|Stream\Xls|Stream\Ods>
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function openFile(string $path): Interface\Stream
    {
        return $this->getFormatProviderClass()::open($path);
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public function fromString(string $data): Interface\Stream
    {
        return match ($this) {
            self::JSON => Stream\Json::string($data),
            self::YAML => Stream\Yaml::string($data),
            self::NEON => Stream\Neon::string($data),
            default => throw new Exception\InvalidFormatException('Unsupported format'),
        };
    }

    /**
     * Returns default values of parameters for concrete format
     * @return array<string, mixed>
     */
    public function getDefaultParams(): array
    {
        return match ($this) {
            self::CSV => ['encoding' => 'utf-8', 'delimiter' => ','],
            self::XML => ['encoding' => 'utf-8'],
            default   => [],
        };
    }

    /**
     * Validate parameters - throw InvalidFormatException when are not valid
     * @param array<string, mixed> $params
     * @throws Exception\InvalidFormatException
     */
    public function validateParams(array $params): void
    {
        match ($this) {
            self::CSV => $this->validateCsvParams($params),
            self::XML => $this->validateXmlParams($params),
            default   => null,
        };
    }

    /**
     * Normalizuje parametry — převede poziční na named, doplní výchozí hodnoty
     * @param array<int, string> $positional
     * @param array<string, string> $named
     * @return array<string, mixed>
     */
    public function normalizeParams(array $positional, array $named): array
    {
        $defaults = $this->getDefaultParams();

        return match ($this) {
            self::CSV => $named !== []
                ? array_merge($defaults, $named)
                : [
                    'encoding'  => $positional[0] ?? $defaults['encoding'],
                    'delimiter' => $positional[1] ?? $defaults['delimiter'],
                ],
            self::XML => $named !== []
                ? array_merge($defaults, $named)
                : ['encoding' => $positional[0] ?? $defaults['encoding']],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $params
     * @throws Exception\InvalidFormatException
     */
    private function validateEncoding(array $params): void
    {
        if (!isset($params['encoding'])) {
            return;
        }

        $encoding = (string) $params['encoding'];
        if ($encoding === '' || @iconv($encoding, 'UTF-8', '') === false) {
            throw new Exception\InvalidFormatException(
                sprintf('Unsupported encoding "%s"', $encoding)
            );
        }
    }

    /**
     * @param array<string, mixed> $params
     * @throws Exception\InvalidFormatException
     */
    private function validateCsvParams(array $params): void
    {
        $this->validateEncoding($params);

        if (isset($params['delimiter']) && strlen((string) $params['delimiter']) !== 1) {
            throw new Exception\InvalidFormatException(
                'CSV delimiter must be a single character'
            );
        }
    }

    /**
     * @param array<string, mixed> $params
     * @throws Exception\InvalidFormatException
     */
    private function validateXmlParams(array $params): void
    {
        $this->validateEncoding($params);
    }
}
