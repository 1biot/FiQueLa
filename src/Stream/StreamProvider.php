<?php

namespace UQL\Stream;

use UQL\Query\Query;

abstract class StreamProvider
{
    abstract public function query(): Query;

    /**
     * Not implemented yet, now it just returns a fetchAll() results from Query/Query instance.
     *
     * @param string $sql
     * @return \Generator
     */
    public function sql(string $sql): \Generator
    {
        // parse SQL and return results
        // return Parser::parse($sql, $this->query())->fetchAll();
        return $this->query()->fetchAll();
    }
}
