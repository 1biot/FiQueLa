<?php

namespace FQL\Stream;

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

    public function fql(string $sql): Interface\Results
    {
        return (new Sql\Sql(trim($sql)))->parseWithQuery($this->query())->execute();
    }
}
