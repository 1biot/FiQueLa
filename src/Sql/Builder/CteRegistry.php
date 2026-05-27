<?php

namespace FQL\Sql\Builder;

use FQL\Exception;
use FQL\Interface;
use FQL\Sql\Ast\Node\CommonTableExpressionNode;
use FQL\Stream\ResultStreamProvider;

/**
 * Per-build registry of Common Table Expressions.
 *
 * Holds the parsed CTE definitions plus the reference counts gathered by
 * {@see CteReferenceCounter}, and turns {@see \FQL\Sql\Ast\Expression\CteReferenceNode}
 * into ready-to-use {@see Interface\Query} instances.
 *
 * ## Resolution strategy
 *
 * The registry weighs **memory** (materialisation buffers all rows in process
 * RAM) against **correctness** (FROM position requires a fresh Query to avoid
 * SELECT field collisions between inner CTE body and outer statement) and
 * **CPU** (multi-reference CTEs win from caching the body's result):
 *
 *  - **Already cached** → return a fresh Query over the cached in-memory stream.
 *  - **FROM position** (`$needsMaterialization = true`) → materialise and cache.
 *    Required: a FROM-position CTE feeds its result through the outer SELECT,
 *    which adds more clauses on the *same* Query instance; if the inner CTE
 *    body already declared fields, those would collide with the outer SELECT.
 *    Materialisation re-anchors the outer onto a clean Query bound to an
 *    `ArrayIterator` of rows.
 *  - **JOIN/UNION position with multi-reference** (refCount ≥ 2) → materialise
 *    on first hit, cache for subsequent references. Avoids rebuilding the body
 *    multiple times.
 *  - **JOIN/UNION position with a single reference** (refCount == 1) → **inline**:
 *    build the body fresh, return it directly, do **not** cache. JOIN and UNION
 *    consume the Query opaquely (no clause-merging into it), so no collision is
 *    possible and we save the materialisation buffer entirely.
 *
 * Lifecycle is scoped to a single `QueryBuildingVisitor::build()` invocation —
 * no global state, no thread-safety concerns.
 *
 * A cycle guard rejects mutually recursive CTE definitions with a clear error
 * (recursive CTEs are out of MVP scope).
 */
final class CteRegistry
{
    /** @var array<string, CommonTableExpressionNode> */
    private array $definitions = [];

    /** @var array<string, int> */
    private array $referenceCounts;

    /** @var array<string, Interface\Stream> */
    private array $materialized = [];

    /**
     * Cache of the pre-materialisation CTE body Queries, keyed by CTE name.
     * Populated lazily by {@see resolve()} on the first resolution of each name
     * so {@see QueryBuildingVisitor} can attach them to the outer Query for
     * toString fidelity (WITH clause rendering) without rebuilding the bodies.
     *
     * @var array<string, Interface\Query>
     */
    private array $resolvedBodies = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /**
     * @param CommonTableExpressionNode[] $commonTables  Definitions, in declaration order.
     * @param array<string, int>          $referenceCounts  Output of {@see CteReferenceCounter}.
     */
    public function __construct(array $commonTables, array $referenceCounts)
    {
        foreach ($commonTables as $cte) {
            $this->definitions[$cte->name] = $cte;
        }
        $this->referenceCounts = $referenceCounts;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function referenceCount(string $name): int
    {
        return $this->referenceCounts[$name] ?? 0;
    }

    /**
     * Resolves a CTE reference into a Query — see class doc for the
     * materialise-vs-inline decision matrix.
     *
     * @param callable(CommonTableExpressionNode): Interface\Query $builder
     *     Callback that builds the CTE body via the enclosing visitor (passing it
     *     in keeps this registry decoupled from {@see QueryBuildingVisitor}).
     * @param bool $needsMaterialization
     *     Caller-asserted requirement that the returned Query must be a fresh,
     *     clause-mergeable instance (FROM position). Single-reference JOIN/UNION
     *     callers pass `false` to opt into inline-without-cache.
     *
     * @throws Exception\QueryLogicException
     * @throws Exception\InvalidFormatException
     */
    public function resolve(string $name, callable $builder, bool $needsMaterialization): Interface\Query
    {
        $definition = $this->definitions[$name] ?? null;
        if ($definition === null) {
            throw new Exception\QueryLogicException(
                sprintf('CTE "%s" is not defined in the enclosing WITH clause', $name)
            );
        }

        // Cache hit — reuse the materialised stream regardless of position.
        if (isset($this->materialized[$name])) {
            return $this->openMaterialisedQuery($name);
        }

        $shouldMaterialize = $needsMaterialization || $this->referenceCount($name) >= 2;

        if (!$shouldMaterialize) {
            // Inline path: hand back the freshly-built CTE Query directly. The caller
            // (JOIN or UNION) consumes it opaquely and never tries to merge further
            // clauses into it, so neither field-collision nor extra memory is at risk.
            $this->guardCycle($name);
            $this->resolving[$name] = true;
            try {
                $body = $builder($definition);
                $this->resolvedBodies[$name] ??= $body;
                return $body;
            } finally {
                unset($this->resolving[$name]);
            }
        }

        $this->guardCycle($name);
        $this->resolving[$name] = true;
        try {
            $query = $builder($definition);
            $this->resolvedBodies[$name] ??= $query;
            /** @var array<int, array<int|string, array<int|string, mixed>|bool|float|int|string|null>> $rows */
            $rows = iterator_to_array($query->execute()->fetchAll(), false);
            // Tag the stream with the CTE name so consuming Query toString renders
            // `FROM <name>` instead of the generic `FROM results(memory)`. The
            // resulting SQL round-trips through the parser thanks to the WITH
            // clause that {@see QueryBuildingVisitor} attaches to the outer Query.
            $this->materialized[$name] = new ResultStreamProvider(new \ArrayIterator($rows), $name);
        } finally {
            unset($this->resolving[$name]);
        }
        return $this->openMaterialisedQuery($name);
    }

    /**
     * Returns the pre-materialisation CTE body Query if it was ever resolved,
     * or null otherwise. Used by {@see QueryBuildingVisitor} to attach CTE
     * definitions to the outer Query so toString preserves the original
     * `WITH` shape.
     */
    public function resolvedBody(string $name): ?Interface\Query
    {
        return $this->resolvedBodies[$name] ?? null;
    }

    /**
     * Anchors the Query on the stream root so the caller can chain `->as($alias)`
     * (mirroring how {@see \FQL\Query\Provider::fromFileQuery()} prepares Query
     * instances).
     */
    private function openMaterialisedQuery(string $name): Interface\Query
    {
        return $this->materialized[$name]->query()->from(Interface\Query::FROM_ALL);
    }

    private function guardCycle(string $name): void
    {
        if (isset($this->resolving[$name])) {
            throw new Exception\QueryLogicException(
                sprintf('Recursive CTE reference detected for "%s" (not supported)', $name)
            );
        }
    }
}
