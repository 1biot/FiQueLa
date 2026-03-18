<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;
use OpenSpout\Reader\ODS\Options;
use OpenSpout\Reader\ODS\Reader;
use OpenSpout\Reader\ReaderInterface;

class Ods extends SpreadsheetProvider
{
    /**
     * @throws Exception\FileNotFoundException
     */
    public static function open(string $path): Interface\Stream
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new Exception\FileNotFoundException('File not found or not readable.');
        }

        return new self($path);
    }

    /**
     * @throws Exception\NotImplementedException
     */
    public static function string(string $data): Interface\Stream
    {
        throw new Exception\NotImplementedException([__CLASS__, __FUNCTION__]);
    }

    /** @phpstan-ignore missingType.generics, return.type */
    protected function createReader(): ReaderInterface
    {
        $options = new Options();
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new Reader($options);
    }

    protected function formatTag(): string
    {
        return 'ods';
    }
}
