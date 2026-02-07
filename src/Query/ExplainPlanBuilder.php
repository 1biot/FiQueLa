<?php

namespace FQL\Query;

use FQL\Enum;
use FQL\Exception\InvalidFormatException;
use FQL\Functions\Core\AggregateFunction;
use FQL\Results;

final class ExplainPlanBuilder
{
    /**
     * @return array<int, array<string,mixed>>
     * @throws InvalidFormatException
     */
    public static function build(Query $query): array
    {
        $s = $query->debugState();

        $steps = [];
        $i = 0;

        $selectedFields = $s['selectedFields'] ?? [];
        $excludedFields = $s['excludedFields'] ?? [];
        $joins          = $s['joins'] ?? [];
        $groupByFields  = $s['groupByFields'] ?? [];
        $orderings      = $s['orderings'] ?? [];
        $limit          = $s['limit'] ?? null;
        $offset         = $s['offset'] ?? null;

        $hasAggregates = self::hasAggregates($selectedFields);
        $isGroupable   = ($groupByFields !== []) || $hasAggregates;
        $isSortable    = ($orderings !== []);
        $isLimitable   = ($limit !== null) || ($offset !== null);
        $hasJoin       = ($joins !== []);

        // 1) SourceScan
        $steps[] = self::row(++$i, 'SourceScan', true, false, [
            'format' => self::detectFormat((string)($s['source'] ?? '')),
            'source' => (string)($s['source'] ?? ''),
            'from' => ($s['from'] ?? null),
            'row_path' => null,
            'stream_provider' => (string)($s['stream_class'] ?? ''),
            'capabilities' => [
                'streaming' => true,
                'seekable' => null,
            ],
        ]);

        // 2) Join(s)
        foreach ($joins as $join) {
            $joinQuery = $join['table'] ?? null; /** @var Query|null $joinQuery */
            $joinSource = $joinQuery ? $joinQuery->provideFileQuery()->__toString() : '';

            $steps[] = self::row(++$i, 'Join', true, true, [
                'join_type' => strtoupper((string)($join['type']->value ?? '')),
                'alias' => (string)($join['alias'] ?? ''),
                'source' => $joinSource,
                'on' => [
                    'left' => (string)($join['leftKey'] ?? ''),
                    'operator' => (string)(($join['operator'] ?? Enum\Operator::EQUAL)->value),
                    'right' => (string)($join['rightKey'] ?? ''),
                ],
                'strategy' => [
                    'type' => 'hashmap',
                    'build_side' => 'right',
                    'key' => (string)($join['rightKey'] ?? ''),
                ],
            ], [
                'Pravá strana JOIN se materializuje do hashmapy (viz Results\\Stream::applyJoin()).',
            ]);
        }

        // 3) WHERE Filter
        $whereExpr = self::renderConditions($s['where'] ?? null);
        if ($whereExpr !== null) {
            $steps[] = self::row(++$i, 'Filter', true, false, [
                'clause' => 'WHERE',
                'expression' => $whereExpr,
                'applied_stage' => 'before_projection',
                'reason' => null,
                'depends_on' => [
                    'fields' => [],
                    'aliases' => [],
                    'functions' => [],
                ],
            ]);
        }

        // 4) Projection (SELECT)
        $projectionDetails = self::projectionDetails(
            (bool)($s['distinct'] ?? false),
            $selectedFields,
            $excludedFields
        );

        $steps[] = self::row(++$i, 'Projection', true, false, $projectionDetails);

        // 5) GroupBy (pokud groupable)
        if ($isGroupable) {
            $steps[] = self::row(++$i, 'GroupBy', true, true, [
                'keys' => $groupByFields,
                'aggregations' => self::aggregateDetails($selectedFields),
                'having' => [
                    'enabled' => self::renderConditions($s['having'] ?? null) !== null,
                    'expression' => self::renderConditions($s['having'] ?? null),
                ],
                'strategy' => [
                    'type' => 'hash_groups',
                    'temp_storage' => 'none',
                ],
            ], [
                'GROUP BY materializuje stav skupin ($groupedData) v paměti (viz applyGrouping()).',
                'Agregace jsou už teď inkrementální (accumulate/finalize).',
            ]);
        }

        // 6) HAVING filter (pokud existuje; u groupable je aplikováno po agregaci, jinak po selectu)
        $havingExpr = self::renderConditions($s['having'] ?? null);
        if ($havingExpr !== null) {
            $steps[] = self::row(++$i, 'Filter', true, false, [
                'clause' => 'HAVING',
                'expression' => $havingExpr,
                'applied_stage' => $isGroupable ? 'after_grouping' : 'after_projection',
                'reason' => null,
                'depends_on' => [
                    'fields' => [],
                    'aliases' => [],
                    'functions' => [],
                ],
            ]);
        }

        // 7) Sort (pokud sortable)
        if ($isSortable) {
            $steps[] = self::row(++$i, 'Sort', false, true, [
                'order_by' => self::orderByDetails($orderings),
                'strategy' => [
                    'type' => 'in_memory_usort',
                    'top_n' => null,
                    'temp_storage' => 'none',
                ],
            ], [
                'ORDER BY materializuje všechna data (iterator_to_array) a řadí usort() (viz applySorting()).',
            ]);
        }

        // 8) LimitOffset (pokud limitable)
        if ($isLimitable) {
            $appliedStage = (!$isSortable) ? 'at_stream' : 'after_sort';
            // kopíruje buildStream(): limit/offset se streamuje jen když není sort
            $steps[] = self::row(++$i, 'LimitOffset', true, false, [
                'limit' => $limit,
                'offset' => $offset,
                'applied_stage' => $appliedStage,
            ]);
        }

        // 9) Result (stejná logika jako Query::execute())
        $forced = ($s['resultClass'] ?? null) !== null;

        $resultClass = $forced
            ? $s['resultClass']
            : (($hasJoin || $isSortable)
                ? Results\InMemory::class
                : Results\Stream::class
            );

        $steps[] = self::row(
            ++$i,
            'Result',
            $resultClass === Results\Stream::class,
            $resultClass === Results\InMemory::class,
            [
                'result_class' => $resultClass,
                'forced' => $forced,
                'reason' => $forced
                    ? 'forced_by_user'
                    : ($hasJoin
                        ? 'join_present'
                        : ($isSortable
                            ? 'order_by_present'
                            : 'streamable'
                        )
                    ),
            ]
        );

        return $steps;
    }

    /** @param array<string,mixed> $details */
    private static function row(int $step, string $op, bool $streaming, bool $materialize, array $details, array $notes = []): array
    {
        return [
            'step' => $step,
            'op' => $op,
            'streaming' => $streaming,
            'materialize' => $materialize,
            'details' => $details,
            'notes' => $notes,
        ];
    }

    /** @param array<string, array{originField:string, alias:bool, function:mixed|null}> $selectedFields */
    private static function hasAggregates(array $selectedFields): bool
    {
        foreach ($selectedFields as $sf) {
            if (($sf['function'] ?? null) instanceof AggregateFunction) {
                return true;
            }
        }
        return false;
    }

    private static function detectFormat(string $source): string
    {
        if (preg_match('/^\[(?<t>[a-zA-Z]{2,8})]/', $source, $m)) {
            return strtolower($m['t']);
        }
        return 'unknown';
    }

    private static function renderConditions(mixed $group): ?string
    {
        if ($group === null) {
            return null;
        }
        // BaseConditionGroup->getConditions() je protected přes GroupCondition, takže jednoduše:
        // render() vrátí "WHERE\n\t..." i pro prázdné? ne, pro prázdné se to v Query::__toString() netiskne,
        // ale tady nemáme přímou kontrolu -> uděláme heuristiku: když render == "WHERE\n\t" tak null.
        $txt = $group->render();
        $trim = trim(str_replace(["\t", "\n", "\r"], ' ', $txt));
        if ($trim === 'WHERE' || $trim === 'HAVING') {
            return null;
        }
        return $txt;
    }

    /** @param array<string, array{originField:string, alias:bool, function:mixed|null}> $selectedFields */
    private static function projectionDetails(bool $distinct, array $selectedFields, array $excludedFields): array
    {
        $selected = [];
        $functions = [];
        $aggregates = [];

        foreach ($selectedFields as $finalField => $sf) {
            $fn = $sf['function'] ?? null;

            $type = 'field';
            if ($fn instanceof AggregateFunction) {
                $type = 'aggregate';
                $aggregates[] = self::shortClass($fn::class);
            } elseif ($fn !== null) {
                $type = 'function';
                $functions[] = self::shortClass($fn::class);
            } elseif (str_contains($finalField, '(')) {
                $type = 'expression';
            }

            $selected[] = [
                'expr' => (string)($sf['originField'] ?? $finalField),
                'alias' => ($sf['alias'] ?? false) ? $finalField : null,
                'type' => $type,
            ];
        }

        $functions = array_values(array_unique($functions));
        $aggregates = array_values(array_unique($aggregates));

        return [
            'distinct' => $distinct,
            'selected' => $selected,
            'exclude' => $excludedFields,
            'functions' => $functions,
            'aggregates' => $aggregates,
        ];
    }

    /** @param array<string, array{originField:string, alias:bool, function:mixed|null}> $selectedFields */
    private static function aggregateDetails(array $selectedFields): array
    {
        $out = [];
        foreach ($selectedFields as $finalField => $sf) {
            $fn = $sf['function'] ?? null;
            if (!$fn instanceof AggregateFunction) {
                continue;
            }
            $out[] = [
                'func' => self::shortClass($fn::class),
                'expr' => (string)($sf['originField'] ?? $finalField),
                'alias' => ($sf['alias'] ?? false) ? $finalField : null,
                'incremental' => true, // u tebe je to už teď accumulate/finalize
            ];
        }
        return $out;
    }

    /** @param array<string, Enum\Sort> $orderings */
    private static function orderByDetails(array $orderings): array
    {
        $out = [];
        foreach ($orderings as $field => $dir) {
            $out[] = ['field' => (string)$field, 'direction' => strtoupper($dir->value)];
        }
        return $out;
    }

    private static function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
