<?php

namespace FQL\Sql\Printer;

use FQL\Sql\Ast\AstNode;
use FQL\Sql\Printer\Internal\Lexical;
use FQL\Sql\Printer\Internal\Renderer;

/**
 * Writes an AST (or any node of it) as FQL text on a single line, without reference to
 * any original source. The output parses back to the same tree:
 *
 * - identifiers are backticked when the tokenizer would not read them as one plain
 *   identifier (spaces, diacritics, keywords), column paths segment by segment;
 * - literals keep their original `raw` spelling when it still matches the value,
 *   otherwise strings use double quotes (single quotes when the value contains `"`),
 *   booleans and NULL are keywords;
 * - a column on the right side of a condition stays a column, arithmetic is written
 *   with operators (`price * 2`), ORDER BY writes only DESC;
 * - a file query keeps its raw text when it matches, otherwise it is written from its
 *   {@see \FQL\Query\FileQuery} (all non-default parameters).
 *
 * Throws {@see UnprintableNodeException} for trees FQL cannot express.
 *
 * Unlike {@see \FQL\Sql\Builder\ExpressionCompiler::renderExpression()}, whose output also
 * names the result columns of unaliased expressions, this printer is meant for writing
 * queries; for edits of an existing query use {@see SourcePrinter}.
 */
final class FqlPrinter
{
    private readonly Renderer $renderer;

    public function __construct(private readonly KeywordCase $keywordCase = KeywordCase::UPPER)
    {
        $this->renderer = new Renderer(null, $keywordCase);
    }

    public function keywordCase(): KeywordCase
    {
        return $this->keywordCase;
    }

    /**
     * @throws UnprintableNodeException
     */
    public function print(AstNode $node): string
    {
        return $this->renderer->render($node);
    }

    /**
     * An alias / CTE name as FQL: bare when it is a plain identifier, otherwise backticked.
     *
     * @throws UnprintableNodeException when the name contains a backtick
     */
    public static function identifier(string $name): string
    {
        return Lexical::identifier($name);
    }

    /**
     * A column path as FQL (`price`, `` `Kód` ``, `` items[].`Název zboží` ``).
     *
     * @throws UnprintableNodeException
     */
    public static function columnReference(string $name, bool $quoted = false): string
    {
        return Lexical::columnReference($name, $quoted);
    }

    /**
     * A string literal as FQL: `"value"`, or `'value'` when it contains a double quote.
     *
     * @throws UnprintableNodeException when it contains both quote characters
     */
    public static function stringLiteral(string $value): string
    {
        return Lexical::stringLiteral($value);
    }
}
