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

        $useHeader = $queryPath->getParam('useHeader');
        if ($useHeader !== null && $stream instanceof Stream\Csv) {
            $stream->useHeader($useHeader === '1');
        }

        $logFormat = $queryPath->getParam('format');
        if ($logFormat !== null && $stream instanceof Stream\AccessLog) {
            $stream->setFormat($logFormat);
        }

        $logPattern = $queryPath->getParam('pattern');
        if ($logPattern !== null && $stream instanceof Stream\AccessLog) {
            $stream->setPattern($logPattern);
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
     * @throws Sql\Parser\ParseException
     */
    public static function fql(string $sql): Interface\Query
    {
        return Sql\Provider::compile($sql)->toQuery();
    }
}
