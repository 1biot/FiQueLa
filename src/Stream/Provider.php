<?php

namespace FQL\Stream;

use FQL\Enum;
use FQL\Exception;
use FQL\Interface;

final class Provider
{
    /**
     * @implements Interface\Stream<Xml|Json|JsonStream|Yaml|Neon|Csv|Xls>
     * @throws Exception\FileNotFoundException
     * @throws Exception\InvalidFormatException
     */
    public static function fromFile(string $path, ?Enum\Format $format = null): Interface\Stream
    {
        $extension = $format ?? Enum\Format::fromExtension(pathinfo($path, PATHINFO_EXTENSION));
        return $extension->openFile($path);
    }

    /**
     * @throws Exception\InvalidFormatException
     */
    public static function fromString(string $data, Enum\Format $format): Interface\Stream
    {
        return $format->fromString($data);
    }
}
