<?php

namespace UQL\Stream;

use UQL\Exceptions\UnexpectedValueException;
use UQL\Parser\Sql;
use UQL\Query\Query;
use UQL\Results\Results;

abstract class StreamProvider
{
    abstract public function query(): Query;

    /**
     * Execute SQL query
     * @throws UnexpectedValueException
     */
    public function sql(string $sql): Results
    {
        // parse SQL and return results
        $query = (new Sql())
            ->parse(trim($sql), $this->query());

        return $query->execute();
    }
}
