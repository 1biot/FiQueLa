<?php

namespace FQL\Sql\Builder;

use FQL\Exception;
use FQL\Interface;
use FQL\Sql\Ast\Node\SelectStatementNode;

/**
 * Convenience facade around QueryBuildingVisitor.
 */
final class QueryBuilder
{
    private readonly QueryBuildingVisitor $visitor;

    public function __construct(?string $basePath = null)
    {
        $this->visitor = new QueryBuildingVisitor(
            new ExpressionCompiler(),
            new FileQueryResolver($basePath)
        );
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function build(SelectStatementNode $ast): Interface\Query
    {
        return $this->visitor->build($ast);
    }

    /**
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function applyTo(SelectStatementNode $ast, Interface\Query $query): Interface\Query
    {
        return $this->visitor->applyTo($ast, $query);
    }

    public function visitor(): QueryBuildingVisitor
    {
        return $this->visitor;
    }
}
