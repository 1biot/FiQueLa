<?php

namespace FQL\Sql\Ast\Node;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Ast\ExplainMode;
use FQL\Sql\Ast\HasSpan;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;

/**
 * Root node for any FQL statement: SELECT, DESCRIBE, or EXPLAIN [ANALYZE] SELECT.
 *
 * `from` is required for SELECT/DESCRIBE; it carries the source reference.
 * `fields` is empty for DESCRIBE (the schema introspection statement).
 */
final readonly class SelectStatementNode implements AstNode, HasSpan
{
    /**
     * @param SelectFieldNode[]              $fields
     * @param JoinClauseNode[]               $joins
     * @param UnionClauseNode[]              $unions
     * @param CommonTableExpressionNode[]    $commonTables
     */
    public function __construct(
        public ?FromClauseNode $from,
        public array $fields,
        public bool $distinct,
        public array $joins,
        public ?WhereClauseNode $where,
        public ?GroupByClauseNode $groupBy,
        public ?HavingClauseNode $having,
        public ?OrderByClauseNode $orderBy,
        public ?LimitClauseNode $limit,
        public array $unions,
        public ?IntoClauseNode $into,
        public bool $describe,
        public ExplainMode $explain,
        public Position $position,
        public array $commonTables = [],
        public ?Span $span = null
    ) {
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function span(): ?Span
    {
        return $this->span;
    }

    /**
     * Copy with another `from` (and no span: it no longer matches the source).
     */
    public function withFrom(?FromClauseNode $from): self
    {
        return new self(
            $from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `fields` (and no span: it no longer matches the source).
     *
     * @param SelectFieldNode[] $fields
     */
    public function withFields(array $fields): self
    {
        return new self(
            $this->from,
            $fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `distinct` (and no span: it no longer matches the source).
     */
    public function withDistinct(bool $distinct): self
    {
        return new self(
            $this->from,
            $this->fields,
            $distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `joins` (and no span: it no longer matches the source).
     *
     * @param JoinClauseNode[] $joins
     */
    public function withJoins(array $joins): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `where` (and no span: it no longer matches the source).
     */
    public function withWhere(?WhereClauseNode $where): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `groupBy` (and no span: it no longer matches the source).
     */
    public function withGroupBy(?GroupByClauseNode $groupBy): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `having` (and no span: it no longer matches the source).
     */
    public function withHaving(?HavingClauseNode $having): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `orderBy` (and no span: it no longer matches the source).
     */
    public function withOrderBy(?OrderByClauseNode $orderBy): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `limit` (and no span: it no longer matches the source).
     */
    public function withLimit(?LimitClauseNode $limit): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `unions` (and no span: it no longer matches the source).
     *
     * @param UnionClauseNode[] $unions
     */
    public function withUnions(array $unions): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `into` (and no span: it no longer matches the source).
     */
    public function withInto(?IntoClauseNode $into): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $into,
            $this->describe,
            $this->explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `explain` (and no span: it no longer matches the source).
     */
    public function withExplain(ExplainMode $explain): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $explain,
            $this->position,
            $this->commonTables
        );
    }

    /**
     * Copy with another `commonTables` (and no span: it no longer matches the source).
     *
     * @param CommonTableExpressionNode[] $commonTables
     */
    public function withCommonTables(array $commonTables): self
    {
        return new self(
            $this->from,
            $this->fields,
            $this->distinct,
            $this->joins,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->limit,
            $this->unions,
            $this->into,
            $this->describe,
            $this->explain,
            $this->position,
            $commonTables
        );
    }
}
