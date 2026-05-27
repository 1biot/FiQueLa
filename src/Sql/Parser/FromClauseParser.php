<?php

namespace FQL\Sql\Parser;

use FQL\Sql\Ast\Expression\CteReferenceNode;
use FQL\Sql\Ast\Expression\ExpressionNode;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Expression\SubQueryNode;
use FQL\Sql\Ast\Node\FromClauseNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

final class FromClauseParser
{
    private StatementParser $statementParser;

    /** @var array<string, true> Names of CTEs visible in the current parse scope. */
    private array $knownCteNames = [];

    public function setStatementParser(StatementParser $parser): void
    {
        $this->statementParser = $parser;
    }

    /**
     * Registers the CTE names that may appear as bare identifiers in FROM/JOIN
     * sources. Caller-managed: {@see WithClauseParser} maintains this incrementally
     * during CTE body parsing, and {@see StatementParser} extends it before parsing
     * the main SELECT body.
     *
     * @param string[] $names
     */
    public function setKnownCteNames(array $names): void
    {
        $this->knownCteNames = array_fill_keys($names, true);
    }

    /**
     * @return string[]
     */
    public function getKnownCteNames(): array
    {
        return array_keys($this->knownCteNames);
    }

    /**
     * Parses the source portion of `FROM <source> [AS alias]` given that the FROM keyword
     * has already been consumed.
     *
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $fromKeyword): FromClauseNode
    {
        $source = $this->parseSource($stream);
        $alias = null;
        if ($stream->consumeIf(TokenType::KEYWORD_AS) !== null) {
            $aliasToken = $stream->expect(TokenType::IDENTIFIER, TokenType::IDENTIFIER_QUOTED);
            $alias = IdentifierHelper::stripOuterBackticks($aliasToken->value);
        }
        return new FromClauseNode($source, $alias, $fromKeyword->position);
    }

    /**
     * @throws ParseException
     */
    public function parseSource(TokenStream $stream): ExpressionNode
    {
        $peek = $stream->peek();
        if ($peek->type === TokenType::PAREN_OPEN) {
            $stream->consume();
            $select = $this->statementParser->parse($stream);
            $stream->expect(TokenType::PAREN_CLOSE);
            return new SubQueryNode($select, $peek->position);
        }

        $token = $stream->expect(TokenType::FILE_QUERY);

        // A bare identifier in source position resolves to a CTE before any FileQuery
        // interpretation. The tokenizer happily parses `active` as a path-only
        // FileQuery, so we must intercept here — otherwise the builder would attempt
        // to open a file named "active".
        if (isset($this->knownCteNames[$token->raw])) {
            return new CteReferenceNode($token->raw, $token->position);
        }

        $fileQuery = $token->metadata;
        if (!$fileQuery instanceof \FQL\Query\FileQuery) {
            // Metadata is attached eagerly by the Tokenizer; if it's absent the FileQuery
            // syntax was malformed at tokenization time (FileQuery construction failed).
            throw ParseException::context($token, 'FROM source (invalid file query)');
        }

        // When CTEs are in scope and the token is a bare identifier (no extension, no
        // dotted path, no `format(...)` prefix), it was almost certainly meant as a
        // CTE reference — surface a targeted error rather than letting the builder
        // fail later with "unsupported file format".
        if (
            $this->knownCteNames !== []
            && $fileQuery->extension === null
            && self::isBareIdentifier($token->raw)
        ) {
            throw ParseException::context(
                $token,
                sprintf(
                    'FROM source (unknown CTE "%s"; declared: %s)',
                    $token->raw,
                    implode(', ', array_keys($this->knownCteNames))
                )
            );
        }
        return new FileQueryNode($fileQuery, $token->raw, $token->position);
    }

    private static function isBareIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }
}
