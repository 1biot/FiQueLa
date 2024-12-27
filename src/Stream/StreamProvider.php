<?php

namespace UQL\Stream;

use UQL\Parser\Sql;
use UQL\Query\Query;

abstract class StreamProvider
{
    abstract public function query(): Query;

    /**
     * Execute SQL query
     *
     * @param string $sql
     * @return \Generator
     */
    public function sql(string $sql): \Generator
    {
        // parse SQL and return results
        $query = (new Sql())
            ->parse(trim($sql), $this->query());

        return $query->fetchAll();
    }
}
