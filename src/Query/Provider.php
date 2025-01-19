<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Enum\Format;
use FQL\Exceptions;
use FQL\Exceptions\FileNotFoundException;
use FQL\Exceptions\InvalidFormatException;
use FQL\Interfaces;
use FQL\Stream;
use FQL\Query;

final class Provider
{
    /**
     * @implements Interfaces\Stream<Stream\Xml|Stream\Json|Stream\JsonStream|Stream\Yaml|Stream\Neon|Stream\Csv>
     * @throws FileNotFoundException
     * @throws InvalidFormatException
     */
    public static function fromFile(string $path, ?Enum\Format $format = null): Interfaces\Stream
    {
        $extension = $format ?? Enum\Format::fromString(pathinfo($path, PATHINFO_EXTENSION));
        return $extension->openFile($path);
    }

    /**
     * @implements Interfaces\Query<Query\Query>
     *     | Interfaces\Stream<Stream\Xml|Stream\Json|Stream\JsonStream|Stream\Yaml|Stream\Neon|Stream\Csv>
     * @throws Exceptions\InvalidFormatException
     * @throws Exceptions\FileNotFoundException
     */
    public static function fromFileQuery(string $fileQuery): Interfaces\Query|Interfaces\Stream
    {
        $queryPath = new FileQuery($fileQuery);
        $stream = self::fromFile($queryPath->file, $queryPath->extension);
        if ($queryPath->query === null) {
            return $stream;
        }

        return $stream->query()
            ->from($queryPath->query);
    }
}
