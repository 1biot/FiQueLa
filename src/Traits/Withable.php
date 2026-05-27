<?php

namespace FQL\Traits;

use FQL\Exception;
use FQL\Interface;

/**
 * Fluent-API counterpart of the FQL `WITH` clause.
 *
 * Registers named Common Table Expressions on a Query so they can be referenced
 * by name when composing larger queries. Use cases:
 *
 *  - **Documentation**: pin a meaningful name on a reusable sub-query.
 *  - **Reuse**: expose the named query to downstream consumers via
 *    {@see getCte()} so JOIN/UNION composition doesn't have to thread a
 *    variable through.
 *
 * Note that in pure fluent API code you can also just pass the sub-query
 * object directly to {@see Joinable::innerJoin()} / {@see Unionable::union()} —
 * the registry is opt-in.
 *
 * Recommended composition for FROM-position CTE usage (which the fluent
 * `from()` setter cannot satisfy because the source stream is immutable per
 * Query instance) is to use {@see \FQL\Sql\Provider::fql()} with a `WITH`
 * statement, where the builder applies the materialise-on-first-use strategy
 * implemented in {@see \FQL\Sql\Builder\CteRegistry}.
 */
trait Withable
{
    /** @var array<string, Interface\Query> */
    private array $ctes = [];

    /**
     * Registers a sub-query under the given name. Subsequent calls with the
     * same name throw — keep the API explicit, mirroring the parser's behaviour
     * for duplicate CTE names in a single WITH clause.
     *
     * @throws Exception\AliasException
     */
    public function with(string $name, Interface\Query $query): Interface\Query
    {
        if ($name === '') {
            throw new Exception\AliasException('CTE name cannot be empty');
        }
        if (isset($this->ctes[$name])) {
            throw new Exception\AliasException(
                sprintf('CTE "%s" is already registered on this query', $name)
            );
        }
        $this->ctes[$name] = $query;
        /** @var Interface\Query $this */
        return $this;
    }

    public function hasCte(string $name): bool
    {
        return isset($this->ctes[$name]);
    }

    public function getCte(string $name): ?Interface\Query
    {
        return $this->ctes[$name] ?? null;
    }

    /**
     * @return array<string, Interface\Query>
     */
    public function getCtes(): array
    {
        return $this->ctes;
    }

    /**
     * Renders the registered CTEs as a `WITH name AS (...)` clause for inclusion
     * in {@see \FQL\Query\Query::__toString()}. Returns an empty string when no
     * CTE is registered, so the caller can concatenate unconditionally.
     */
    private function ctesToString(): string
    {
        if ($this->ctes === []) {
            return '';
        }

        $parts = [];
        foreach ($this->ctes as $name => $cteQuery) {
            $body = trim((string) $cteQuery);
            $parts[] = sprintf("%s %s (\n%s\n)", $name, Interface\Query::AS, $body);
        }

        return Interface\Query::WITH . ' ' . implode(",\n", $parts) . "\n";
    }
}
