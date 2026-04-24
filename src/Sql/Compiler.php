<?php

namespace FQL\Sql;

use FQL\Exception;
use FQL\Interface;
use FQL\Sql\Ast\Node\SelectStatementNode;
use FQL\Sql\Builder\QueryBuilder;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Parser\Parser;
use FQL\Sql\Token\Tokenizer;
use FQL\Sql\Token\TokenStream;

/**
 * Orchestrates the full FQL compilation pipeline: source string → tokens → AST → Query.
 *
 * Construction is cheap; each public method creates its own tokens/AST as needed. The
 * compiler caches intermediate artifacts for a single SQL input so that callers who
 * need both tokens and the AST (or AST and a Query) do not re-tokenize.
 */
final class Compiler
{
    /** @var \FQL\Sql\Token\Token[]|null */
    private ?array $tokens = null;
    private ?SelectStatementNode $ast = null;
    private ?Parser $parser = null;

    public function __construct(
        private readonly string $sql,
        private readonly ?string $basePath = null,
        private readonly Tokenizer $tokenizer = new Tokenizer(),
        private readonly ?QueryBuilder $builder = null
    ) {
    }

    /**
     * @throws ParseException
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function toQuery(): Interface\Query
    {
        $ast = $this->toAst();
        return $this->resolveBuilder()->build($ast);
    }

    /**
     * Applies the compiled AST onto an existing Interface\Query (replacement for the
     * legacy `Sql::parseWithQuery()`). Used when a stream-backed Query is already held
     * and the SQL should extend it with additional clauses rather than open a new file.
     *
     * @throws ParseException
     * @throws Exception\InvalidFormatException
     * @throws Exception\FileNotFoundException
     */
    public function applyTo(Interface\Query $query): Interface\Query
    {
        $ast = $this->toAst();
        return $this->resolveBuilder()->applyTo($ast, $query);
    }

    /**
     * @throws ParseException
     */
    public function toAst(): SelectStatementNode
    {
        if ($this->ast === null) {
            $this->ast = $this->resolveParser()->parse($this->toTokenStream());
        }
        return $this->ast;
    }

    public function toTokenStream(bool $includeTrivia = false): TokenStream
    {
        return new TokenStream($this->toTokens(), $includeTrivia);
    }

    /**
     * @return \FQL\Sql\Token\Token[]
     */
    public function toTokens(): array
    {
        if ($this->tokens === null) {
            $this->tokens = $this->tokenizer->tokenize($this->sql);
        }
        return $this->tokens;
    }

    public function getBasePath(): ?string
    {
        return $this->basePath;
    }

    private function resolveBuilder(): QueryBuilder
    {
        return $this->builder ?? new QueryBuilder($this->basePath);
    }

    private function resolveParser(): Parser
    {
        return $this->parser ??= Parser::create();
    }
}
