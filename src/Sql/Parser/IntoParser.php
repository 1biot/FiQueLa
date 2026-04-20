<?php

namespace FQL\Sql\Parser;

use FQL\Query\FileQuery;
use FQL\Sql\Ast\Expression\FileQueryNode;
use FQL\Sql\Ast\Node\IntoClauseNode;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;

final class IntoParser
{
    /**
     * @throws ParseException
     */
    public function parseClause(TokenStream $stream, Token $intoKeyword): IntoClauseNode
    {
        $token = $stream->expect(TokenType::FILE_QUERY);
        $fileQuery = $token->metadata;
        if (!$fileQuery instanceof FileQuery) {
            throw ParseException::context($token, 'INTO target (invalid file query)');
        }
        $target = new FileQueryNode($fileQuery, $token->raw, $token->position);
        return new IntoClauseNode($target, $intoKeyword->position);
    }
}
