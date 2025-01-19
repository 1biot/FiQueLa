<?php

namespace FQL\Stream;

use FQL\Interfaces;
use FQL\Query;
use FQL\Sql;

abstract class StreamProvider implements Interfaces\Stream
{
    public function query(): Interfaces\Query
    {
        return new Query\Query($this);
    }

    public function fql(string $sql): Interfaces\Results
    {
        return (new Sql\Sql())
            ->parse(trim($sql), $this->query())
            ->execute();
    }
}
