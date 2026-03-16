<?php

namespace Cli\Output;

use FQL\Cli\Output\TableRenderer;
use FQL\Cli\Query\QueryResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

class TableRendererTest extends TestCase
{
    private TableRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TableRenderer(50);
    }

    public function testFormatRowsTruncatesLongValues(): void
    {
        $data = [
            ['col1' => str_repeat('a', 100), 'col2' => 'short'],
        ];

        $formatted = $this->renderer->formatRows($data);

        $this->assertCount(1, $formatted);
        $this->assertEquals(53, mb_strlen($formatted[0][0])); // 50 chars + "..."
        $this->assertEquals('short', $formatted[0][1]);
    }

    public function testFormatRowsHandlesNull(): void
    {
        $data = [
            ['col1' => null, 'col2' => 'value'],
        ];

        $formatted = $this->renderer->formatRows($data);

        $this->assertEquals('null', $formatted[0][0]);
        $this->assertEquals('value', $formatted[0][1]);
    }

    public function testFormatRowsHandlesArrays(): void
    {
        $data = [
            ['col1' => ['nested' => 'value', 'list' => [1, 2, 3]]],
        ];

        $formatted = $this->renderer->formatRows($data);

        $expected = json_encode(['nested' => 'value', 'list' => [1, 2, 3]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertEquals($expected, $formatted[0][0]);
    }

    public function testFormatRowsHandlesNumericValues(): void
    {
        $data = [
            ['col1' => 42, 'col2' => 3.14],
        ];

        $formatted = $this->renderer->formatRows($data);

        $this->assertEquals('42', $formatted[0][0]);
        $this->assertEquals('3.14', $formatted[0][1]);
    }

    public function testFormatRowsCustomTruncateLength(): void
    {
        $renderer = new TableRenderer(10);
        $data = [
            ['col1' => 'This is a longer string than ten characters'],
        ];

        $formatted = $renderer->formatRows($data);

        $this->assertEquals('This is a ...', $formatted[0][0]);
    }

    public function testHumanFilesize(): void
    {
        $this->assertEquals('0 B', TableRenderer::humanFilesize(0));
        $this->assertEquals('1 B', TableRenderer::humanFilesize(1));
        $this->assertEquals('1 KB', TableRenderer::humanFilesize(1024));
        $this->assertEquals('1.5 KB', TableRenderer::humanFilesize(1536));
        $this->assertEquals('1 MB', TableRenderer::humanFilesize(1048576));
        $this->assertEquals('1 GB', TableRenderer::humanFilesize(1073741824));
    }

    public function testRenderAndHighlightText(): void
    {
        $stream = fopen('php://memory', 'w+');
        $sections = [];
        $section = new ConsoleSectionOutput($stream, $sections, OutputInterface::VERBOSITY_NORMAL, false, new OutputFormatter(false));

        $result = new QueryResult(
            [
                ['id' => '1', 'name' => 'Alice'],
                ['id' => '2', 'name' => 'Bob'],
            ],
            ['id', 'name'],
            2,
            0.0123
        );

        $this->renderer->render($section, $result, 1, 1, 25);
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        $this->assertStringContainsString('Page 1/1', $content);
        $this->assertStringContainsString('Alice', $content);

        $this->renderer->highlightText($section, 'Alice');
        rewind($stream);
        $highlighted = (string) stream_get_contents($stream);
        $this->assertStringContainsString('Alice', $highlighted);
    }

    public function testRenderSinglePage(): void
    {
        $stream = fopen('php://memory', 'w+');
        $sections = [];
        $section = new ConsoleSectionOutput($stream, $sections, OutputInterface::VERBOSITY_NORMAL, false, new OutputFormatter(false));

        $result = new QueryResult(
            [
                ['id' => '1', 'name' => 'Alice'],
            ],
            ['id', 'name'],
            1,
            0.001
        );

        $this->renderer->renderSinglePage($section, $result);
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        $this->assertStringContainsString('Page 1/1', $content);
        $this->assertStringContainsString('Alice', $content);
    }
}
