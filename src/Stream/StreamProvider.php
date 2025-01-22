<?php

namespace FQL\Stream;

use FQL\Interface;
use FQL\Query;
use FQL\Sql;

abstract class StreamProvider implements Interface\Stream
{
    public function query(): Interface\Query
    {
        return new Query\Query($this);
    }

    public function fql(string $sql): Interface\Results
    {
        return (new Sql\Sql())
            ->parse(trim($sql), $this->query())
            ->execute();
    }
}
