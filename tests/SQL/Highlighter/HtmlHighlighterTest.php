<?php

namespace SQL\Highlighter;

use FQL\Sql\Highlighter\HighlighterKind;
use FQL\Sql\Highlighter\HtmlHighlighter;
use FQL\Sql\Highlighter\HtmlTheme;
use FQL\Sql\Provider as SqlProvider;
use FQL\Sql\Token\TokenType;
use PHPUnit\Framework\TestCase;

class HtmlHighlighterTest extends TestCase
{
    private HtmlHighlighter $highlighter;

    protected function setUp(): void
    {
        $this->highlighter = new HtmlHighlighter();
    }

    public function testWrapsKeywordsInSpans(): void
    {
        $out = $this->highlighter->highlight('SELECT id FROM x');
        $this->assertStringContainsString('<span class="fql-keyword">SELECT</span>', $out);
        $this->assertStringContainsString('<span class="fql-keyword">FROM</span>', $out);
    }

    public function testWrapsFunctionNames(): void
    {
        $out = $this->highlighter->highlight('SELECT COUNT(id) FROM x');
        $this->assertStringContainsString('<span class="fql-function">COUNT</span>', $out);
    }

    public function testWrapsFileQuery(): void
    {
        $out = $this->highlighter->highlight('SELECT * FROM json(data.json)');
        $this->assertStringContainsString('<span class="fql-file-query">json(data.json)</span>', $out);
    }

    public function testEscapesHtmlInStringLiterals(): void
    {
        $out = $this->highlighter->highlight("SELECT '<script>' FROM x");
        // Angle brackets must be encoded so the highlighted output is safe to embed.
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringNotContainsString('<script>', $out);
    }

    public function testEscapesHtmlInIdentifiers(): void
    {
        $out = $this->highlighter->highlight('SELECT `<tag>` FROM x');
        $this->assertStringContainsString('&lt;tag&gt;', $out);
    }

    public function testLogicalKeywordsUseLogicalClass(): void
    {
        $out = $this->highlighter->highlight('SELECT * FROM x WHERE a > 1 AND b IS NULL');
        $this->assertStringContainsString('<span class="fql-keyword-logical">AND</span>', $out);
        $this->assertStringContainsString('<span class="fql-keyword-logical">IS</span>', $out);
    }

    public function testLiteralsHaveDistinctClasses(): void
    {
        $out = $this->highlighter->highlight('SELECT 42, TRUE, NULL, "x" FROM x');
        $this->assertStringContainsString('<span class="fql-number">42</span>', $out);
        $this->assertStringContainsString('<span class="fql-boolean">TRUE</span>', $out);
        $this->assertStringContainsString('<span class="fql-null">NULL</span>', $out);
        $this->assertStringContainsString('<span class="fql-string">&quot;x&quot;</span>', $out);
    }

    public function testPunctuationIsWrapped(): void
    {
        $out = $this->highlighter->highlight('SELECT COUNT(*) FROM x');
        $this->assertStringContainsString('<span class="fql-punctuation">(</span>', $out);
        $this->assertStringContainsString('<span class="fql-punctuation">)</span>', $out);
        $this->assertStringContainsString('<span class="fql-punctuation">*</span>', $out);
    }

    public function testOperatorsAreWrapped(): void
    {
        $out = $this->highlighter->highlight('SELECT * FROM x WHERE a = 1');
        $this->assertStringContainsString('<span class="fql-operator">=</span>', $out);
    }

    public function testCommentsAreWrapped(): void
    {
        $sql = "SELECT 1 /* block */ FROM x";
        $out = $this->highlighter->highlight($sql);
        $this->assertStringContainsString('<span class="fql-comment">/* block */</span>', $out);
    }

    public function testThemeUnstyledTypeHasEmptyMarkers(): void
    {
        $theme = new HtmlTheme();
        // EOF has no style marker.
        $this->assertSame('', $theme->styleStart(TokenType::EOF));
        $this->assertSame('', $theme->styleEnd(TokenType::EOF));
    }

    public function testThemeEscapeHandlesSpecialChars(): void
    {
        $theme = new HtmlTheme();
        $this->assertSame('&amp;&quot;&lt;&gt;', $theme->escape('&"<>'));
    }

    public function testProviderHighlightHtmlMatchesDirectHighlighter(): void
    {
        $sql = 'SELECT id FROM x';
        $this->assertSame(
            $this->highlighter->highlight($sql),
            SqlProvider::highlight($sql, HighlighterKind::HTML)
        );
    }
}
