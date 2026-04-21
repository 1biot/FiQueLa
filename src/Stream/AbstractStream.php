<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;
use FQL\Query;
use FQL\Sql;

abstract class AbstractStream implements Interface\Stream
{
    /**
     * @return Query\Query
     */
    public function query(): Interface\Query
    {
        return new Query\Query($this);
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     * @throws Sql\Parser\ParseException
     */
    public function fql(string $sql): Interface\Results
    {
        return Sql\Provider::compile($sql)
            ->applyTo($this->query())
            ->execute();
    }
}
