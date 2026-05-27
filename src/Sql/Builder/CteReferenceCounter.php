<?php

namespace FQL\Sql\Builder;

use FQL\Sql\Ast\Expression\CteReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\Node\SelectStatementNode;

/**
 * Walks a {@see SelectStatementNode} and tallies how many times each CTE name is
 * referenced from FROM/JOIN sources (transitively via nested subqueries and UNION
 * branches). The result drives the inline-vs-materialize decision in
 * {@see CteRegistry}.
 *
 * Nested CTE definitions themselves are also walked, so a reference in CTE B's
 * body to CTE A is counted for the purpose of deciding A's strategy.
 */
final class CteReferenceCounter
{
    /**
     * @return array<string, int>
     */
    public function count(SelectStatementNode $statement): array
    {
        $counts = [];
        $this->walkStatement($statement, $counts);
        return $counts;
    }

    /**
     * @param array<string, int> $counts
     */
    private function walkStatement(SelectStatementNode $statement, array &$counts): void
    {
        if ($statement->from !== null) {
            $this->walkSource($statement->from->source, $counts);
        }

        foreach ($statement->joins as $join) {
            $this->walkSource($join->source, $counts);
        }

        foreach ($statement->unions as $union) {
            $this->walkStatement($union->query, $counts);
        }

        // Nested CTE bodies may themselves reference earlier CTEs in the same WITH.
        foreach ($statement->commonTables as $cte) {
            $this->walkStatement($cte->query, $counts);
        }
    }

    /**
     * @param array<string, int> $counts
     */
    private function walkSource(ExpressionNode $source, array &$counts): void
    {
        if ($source instanceof CteReferenceNode) {
            $counts[$source->name] = ($counts[$source->name] ?? 0) + 1;
            return;
        }
        if ($source instanceof SubQueryNode) {
            $this->walkStatement($source->query, $counts);
        }
    }
}
