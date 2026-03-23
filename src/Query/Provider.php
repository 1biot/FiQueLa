<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exception;
use FQL\Interface;
use FQL\Stream;
use FQL\Query;
use FQL\Sql;

final class Provider
{
    /**
     * @implements Interface\Query<Query\Query>
     * @throws Exception\FileNotFoundException
     * @throws Exception\InvalidFormatException
     */
    public static function fromFile(string $path, ?Enum\Format $format = null): Interface\Query
    {
        return Stream\Provider::fromFile($path, $format)->query();
    }

    /**
     * @implements Interface\Query<Query\Query>
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public static function fromFileQuery(string $fileQuery): Interface\Query
    {
        $queryPath = new FileQuery($fileQuery);
        $stream = Stream\Provider::fromFile($queryPath->file ?? '', $queryPath->extension);

        $encoding = $queryPath->getParam('encoding');
        if ($encoding !== null && ($stream instanceof Stream\Xml || $stream instanceof Stream\Csv)) {
            $stream->setInputEncoding($encoding);
        }

        $delimiter = $queryPath->getParam('delimiter');
        if ($delimiter !== null && $delimiter !== ',' && $stream instanceof Stream\Csv) {
            $stream->setDelimiter($delimiter);
        }

        if ($queryPath->query === null) {
            return $stream->query();
        }

        return $stream->query()->from($queryPath->query);
    }

    /**
     * @implements Interface\Query<Query\Query>
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public static function fql(string $sql): Interface\Query
    {
        return (new Sql\Sql(trim($sql)))
            ->toQuery();
    }
}
