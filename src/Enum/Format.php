<?php

namespace FQL\Enum;

use FQL\Exceptions;
use FQL\Exceptions\InvalidFormatException;
use FQL\Interfaces;
use FQL\Stream;

enum Format: string
{
    case XML = 'xml';
    case JSON = 'json';
    case JSON_STREAM = 'jsonFile';
    case CSV = 'csv';
    case YAML = 'yaml';
    case NEON = 'neon';

    /**
     * @return class-string<Stream\Csv|Stream\Json|Stream\JsonStream|Stream\Xml|Stream\Neon|Stream\Yaml>
     */
    public function getFormatProviderClass(): string
    {
        return match ($this) {
            self::XML => Stream\Xml::class,
            self::JSON => Stream\Json::class,
            self::JSON_STREAM => Stream\JsonStream::class,
            self::CSV => Stream\Csv::class,
            self::YAML => Stream\Yaml::class,
            self::NEON => Stream\Neon::class,
        };
    }

    /**
     * @throws InvalidFormatException
     */
    public static function fromString(string $format): self
    {
        return match ($format) {
            'xml' => self::XML,
            'json' => self::JSON,
            'jsonFile' => self::JSON_STREAM,
            'csv' => self::CSV,
            'yaml' => self::YAML,
            'neon' => self::NEON,
            default => throw new Exceptions\InvalidFormatException('Unsupported file format'),
        };
    }

    /**
     * @implements Interfaces\Stream<Stream\Xml|Stream\Json|Stream\JsonStream|Stream\Yaml|Stream\Neon|Stream\Csv>
     * @throws Exceptions\InvalidFormatException
     * @throws Exceptions\FileNotFoundException
     */
    public function openFile(string $path): Interfaces\Stream
    {
        return $this->getFormatProviderClass()::open($path);
    }
}
