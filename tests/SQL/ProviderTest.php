<?php

namespace SQL;

use FQL\Sql\Ast\Expression\BinaryOpNode;
use FQL\Sql\Ast\Expression\ColumnReferenceNode;
use FQL\Sql\Ast\Expression\ConditionGroupNode;
use FQL\Sql\Formatter\FormatterOptions;
use FQL\Sql\Highlighter\HighlighterKind;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Provider;
use FQL\Sql\Token\TokenStream;
use FQL\Sql\Token\TokenType;
use PHPUnit\Framework\TestCase;

/**
 * Covers the static fasádes on Sql\Provider that tie the tokenizer, parser,
 * formatter, highlighter, linter and the public fragment parsers together.
 * The pipeline itself is exercised by integration tests elsewhere — this
 * suite ensures each Provider-level entry point delegates correctly and
 * surfaces its error paths.
 */
class ProviderTest extends TestCase
{
    public function testCompileReturnsCompiler(): void
    {
        $compiler = Provider::compile('SELECT * FROM json(x)');
        $this->assertNotNull($compiler->toAst());
    }

    public function testTokenizeReturnsStream(): void
    {
        $stream = Provider::tokenize('SELECT 1');
        $this->assertInstanceOf(TokenStream::class, $stream);
        // First meaningful token is the SELECT keyword.
        $first = $stream->peek();
        $this->assertSame(TokenType::KEYWORD_SELECT, $first->type);
    }

    public function testTokenizeWithoutTriviaOmitsWhitespace(): void
    {
        $stream = Provider::tokenize('SELECT   1', includeTrivia: false);
        $count = 0;
        while (!$stream->isAtEnd()) {
            $stream->consume();
            $count++;
        }
        // 2 tokens (SELECT, 1) plus EOF
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testHighlightBashDefault(): void
    {
        $out = Provider::highlight('SELECT a FROM json(x)');
        // Default is BASH — output must contain ANSI escape sequences.
        $this->assertStringContainsString("\033[", $out);
    }

    public function testHighlightHtml(): void
    {
        $out = Provider::highlight('SELECT a FROM json(x)', HighlighterKind::HTML);
        $this->assertStringContainsString('<span class="fql-', $out);
        $this->assertStringNotContainsString("\033[", $out);
    }

    public function testFormatUsesDefaultOptions(): void
    {
        $out = Provider::format('select a, b from json(x)');
        $this->assertStringContainsString('SELECT', $out);
        $this->assertStringContainsString('FROM', $out);
    }

    public function testFormatWithCustomOptions(): void
    {
        $out = Provider::format(
            'SELECT a,b FROM json(x)',
            new FormatterOptions(indent: '  ', uppercaseKeywords: true, fieldsPerLine: true, newline: "\n")
        );
        $this->assertStringContainsString("  a,\n  b", $out);
    }

    public function testParseExpressionColumnReference(): void
    {
        $node = Provider::parseExpression('price');
        $this->assertInstanceOf(ColumnReferenceNode::class, $node);
        $this->assertSame('price', $node->name);
    }

    public function testParseExpressionArithmetic(): void
    {
        $node = Provider::parseExpression('price * 1.21');
        $this->assertInstanceOf(BinaryOpNode::class, $node);
    }

    public function testParseExpressionRejectsTrailingTokens(): void
    {
        $this->expectException(ParseException::class);
        // Trailing `, extra` past a single expression must blow up.
        Provider::parseExpression('price, extra');
    }

    public function testParseConditionSimple(): void
    {
        $node = Provider::parseCondition('price > 100');
        $this->assertInstanceOf(ConditionGroupNode::class, $node);
    }

    public function testParseConditionRejectsTrailingTokens(): void
    {
        $this->expectException(ParseException::class);
        Provider::parseCondition('price > 100 extraGibberish');
    }

    public function testLintReturnsReport(): void
    {
        $report = Provider::lint('SELECT * FROM json(x)');
        $this->assertFalse($report->hasErrors());
    }

    public function testLintCheckFilesystemFlagFlipsRule(): void
    {
        $report = Provider::lint('SELECT * FROM csv(/definitely/not/a/real/path.csv)', checkFilesystem: true);
        $this->assertTrue($report->hasErrors());
    }
}
