<?php

namespace SQL\Highlighter;

use FQL\Sql\Highlighter\BashHighlighter;
use FQL\Sql\Highlighter\BashTheme;
use FQL\Sql\Highlighter\HighlighterKind;
use FQL\Sql\Provider as SqlProvider;
use FQL\Sql\Token\TokenType;
use PHPUnit\Framework\TestCase;

class BashHighlighterTest extends TestCase
{
    private BashHighlighter $highlighter;

    protected function setUp(): void
    {
        $this->highlighter = new BashHighlighter();
    }

    public function testWrapsKeywordsInAnsi(): void
    {
        $out = $this->highlighter->highlight('SELECT id FROM x');
        $this->assertStringContainsString("\e[1;36mSELECT\e[0m", $out);
        $this->assertStringContainsString("\e[1;36mFROM\e[0m", $out);
    }

    public function testWrapsFunctionNames(): void
    {
        $out = $this->highlighter->highlight('SELECT COUNT(*) FROM x');
        $this->assertStringContainsString("\e[35mCOUNT\e[0m", $out);
    }

    public function testWrapsFileQuery(): void
    {
        $out = $this->highlighter->highlight('SELECT * FROM json(data.json).rows');
        $this->assertStringContainsString("\e[1;34mjson(data.json).rows\e[0m", $out);
    }

    public function testWrapsStringLiteral(): void
    {
        $out = $this->highlighter->highlight("SELECT 'hi' FROM x");
        $this->assertStringContainsString("\e[32m'hi'\e[0m", $out);
    }

    public function testWrapsNumberAndBooleanAndNull(): void
    {
        $out = $this->highlighter->highlight('SELECT 42, TRUE, NULL FROM x');
        $this->assertStringContainsString("\e[36m42\e[0m", $out);
        $this->assertStringContainsString("\e[36mTRUE\e[0m", $out);
        $this->assertStringContainsString("\e[36mNULL\e[0m", $out);
    }

    public function testLogicalKeywordsUseYellow(): void
    {
        $out = $this->highlighter->highlight('SELECT * FROM x WHERE a > 1 AND b IS NULL');
        $this->assertStringContainsString("\e[1;33mAND\e[0m", $out);
        $this->assertStringContainsString("\e[1;33mIS\e[0m", $out);
    }

    public function testPreservesWhitespaceAndComments(): void
    {
        $sql = "SELECT id -- hi\n  FROM x";
        $out = $this->highlighter->highlight($sql);
        // Comment is styled grey but whitespace is emitted untouched.
        $this->assertStringContainsString("\e[2;37m-- hi\e[0m", $out);
        $this->assertStringContainsString("\n  ", $out);
    }

    public function testIdentifiersHaveNoStyling(): void
    {
        $out = $this->highlighter->highlight('SELECT id FROM x');
        // Identifier `id` appears without escape wrapping — the preceding token emits
        // its reset, then a plain " id " follows.
        $this->assertStringContainsString(' id ', $out);
    }

    public function testThemeEndReturnsResetOnlyForStyledTypes(): void
    {
        $theme = new BashTheme();
        $this->assertSame("\e[0m", $theme->styleEnd(TokenType::KEYWORD_SELECT));
        $this->assertSame('', $theme->styleEnd(TokenType::IDENTIFIER));
        $this->assertSame('', $theme->styleEnd(TokenType::WHITESPACE));
    }

    public function testThemeEscapeIsIdentity(): void
    {
        $theme = new BashTheme();
        $this->assertSame('<x>', $theme->escape('<x>'));
    }

    public function testProviderHighlightBashMatchesDirectHighlighter(): void
    {
        $sql = 'SELECT id FROM x';
        $this->assertSame(
            $this->highlighter->highlight($sql),
            SqlProvider::highlight($sql, HighlighterKind::BASH)
        );
    }

    public function testProviderDefaultKindIsBash(): void
    {
        $sql = 'SELECT * FROM x';
        $this->assertSame($this->highlighter->highlight($sql), SqlProvider::highlight($sql));
    }
}
