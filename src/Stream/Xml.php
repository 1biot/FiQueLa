<?php

namespace UQL\Stream;

use UQL\Exceptions\FileNotFoundException;
use UQL\Exceptions\InvalidFormat;
use UQL\Query\Provider;
use UQL\Query\Query;

class Xml extends XmlProvider
{
    /**
     * @throws FileNotFoundException
     * @throws InvalidFormat
     */
    public static function openWithEncoding(string $path, ?string $encoding = null): self
    {
        if (file_exists($path) === false || is_readable($path) === false) {
            throw new FileNotFoundException("File not found or not readable.");
        }

        $xmlReader = new \XMLReader();

        if (!$xmlReader->open($path, $encoding)) {
            throw new InvalidFormat("Could not open a file");
        }

        $xmlReader->setParserProperty(\XMLReader::VALIDATE, true);
        if (!$xmlReader->isValid()) {
            throw new InvalidFormat("Invalid XML file");
        }

        return new self($xmlReader);
    }

    /**
     * @throws FileNotFoundException
     * @throws InvalidFormat
     */
    public static function open(string $path): self
    {
        return self::openWithEncoding($path);
    }

    /**
     * @throws InvalidFormat
     */
    public static function string(string $data): Stream
    {
        $xmlReader = \XMLReader::XML($data, null, LIBXML_DTDVALID);
        if (!$xmlReader) {
            throw new InvalidFormat("Invalid XML string");
        }

        return new self($xmlReader);
    }

    public function query(): Query
    {
        return new Provider($this);
    }
}
