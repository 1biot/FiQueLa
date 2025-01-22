<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exception;
use FQL\Exception\FileNotFoundException;
use FQL\Exception\InvalidFormatException;
use FQL\Interface;
use FQL\Stream;
use FQL\Query;

final class Provider
{
    /**
     * @implements Interface\Stream<Stream\Xml|Stream\Json|Stream\JsonStream|Stream\Yaml|Stream\Neon|Stream\Csv>
     * @throws FileNotFoundException
     * @throws InvalidFormatException
     */
    public static function fromFile(string $path, ?Enum\Format $format = null): Interface\Stream
    {
        $extension = $format ?? Enum\Format::fromString(pathinfo($path, PATHINFO_EXTENSION));
        return $extension->openFile($path);
    }

    /**
     * @implements Interface\Query<Query\Query>
     *     | Interfaces\Stream<Stream\Xml|Stream\Json|Stream\JsonStream|Stream\Yaml|Stream\Neon|Stream\Csv>
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public static function fromFileQuery(string $fileQuery): Interface\Query|Interface\Stream
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
