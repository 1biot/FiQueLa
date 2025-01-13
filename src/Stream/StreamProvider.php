<?php

namespace FQL\Stream;

use FQL\Exceptions\UnexpectedValueException;
use FQL\Parser\Sql;
use FQL\Query\Query;
use FQL\Results\Results;

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
