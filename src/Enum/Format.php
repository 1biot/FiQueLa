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
    case DIR = 'dir';

    /**
     * @return class-string<Stream\Csv|Stream\Json|Stream\JsonStream|Stream\Xml|Stream\Neon|Stream\Yaml|Stream\NDJson|Stream\Dir>
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
            self::DIR => Stream\Dir::class,
        };
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public static function fromExtension(string $extension): self
    {
        return match ($extension) {
            'xml' => self::XML,
            'json', 'jsonFile' => self::JSON_STREAM,
            'ndjson' => self::ND_JSON,
            'csv', 'tsv' => self::CSV,
            'yaml', 'yml' => self::YAML,
            'neon' => self::NEON,
            'dir' => self::DIR,
            default => throw new Exception\InvalidFormatException(sprintf('Unsupported file format "%s"', $extension)),
        };
    }

    /**
     * @implements Interface\Stream<Stream\Xml|Stream\Json|Stream\JsonStream|Stream\Yaml|Stream\Neon|Stream\Csv>
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
}
