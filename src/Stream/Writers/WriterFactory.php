<?php

namespace FQL\Stream\Writers;

use FQL\Enum\Format;
use FQL\Exception\FileAlreadyExistsException;
use FQL\Exception\InvalidFormatException;
use FQL\Interface\Writer;
use FQL\Query\FileQuery;

class WriterFactory
{
    public static function create(FileQuery $fileQuery): Writer
    {
        if ($fileQuery->file !== null && file_exists($fileQuery->file)) {
            throw new FileAlreadyExistsException(sprintf('File "%s" already exists', $fileQuery->file));
        }

        return match ($fileQuery->extension) {
            Format::CSV => new CsvWriter($fileQuery),
            Format::ND_JSON => new NdJsonWriter($fileQuery),
            Format::JSON,
            Format::JSON_STREAM => new JsonWriter($fileQuery),
            Format::XML => new XmlWriter($fileQuery),
            Format::XLS => new XlsxWriter($fileQuery),
            Format::ODS => new OdsWriter($fileQuery),
            default => throw new InvalidFormatException(
                sprintf('Format "%s" does not support writing', $fileQuery->format)
            ),
        };
    }
}
