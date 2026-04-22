<?php

namespace FQL\Sql\Parser;

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

    public function setStatementParser(StatementParser $parser): void
    {
        $this->statementParser = $parser;
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
        $fileQuery = $token->metadata;
        if (!$fileQuery instanceof \FQL\Query\FileQuery) {
            // Metadata is attached eagerly by the Tokenizer; if it's absent the FileQuery
            // syntax was malformed at tokenization time (FileQuery construction failed).
            throw ParseException::context($token, 'FROM source (invalid file query)');
        }
        return new FileQueryNode($fileQuery, $token->raw, $token->position);
    }
}
