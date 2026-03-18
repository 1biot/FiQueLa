<?php

namespace Traits\Helpers;

use PHPUnit\Framework\TestCase;
use FQL\Traits\Helpers\StringOperations;

class StringOperationsTest extends TestCase
{
    use StringOperations;

    public function testCamelCaseToUpperSnakeCase(): void
    {
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('HelloWorld'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('helloWorld'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('Hello_World'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('hello_world'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('hello__world'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('Hello__World'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('hello___world'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('Hello___World'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('HelloWORLD'));
        $this->assertEquals('HELLO_WORLD', $this->camelCaseToUpperSnakeCase('HELLO_WORLD'));
    }

    public function testIsQuoted(): void
    {
        $this->assertTrue($this->isQuoted('"HELLO_WORLD"'));
        $this->assertTrue($this->isQuoted('\'HELLO_WORLD\''));
        $this->assertTrue($this->isQuoted("'HELLO_WORLD'"));
        $this->assertFalse($this->isQuoted("`HELLO_WORLD`"));
    }

    public function testIsBacktick(): void
    {
        $this->assertTrue($this->isBacktick('`HELLO_WORLD`'));
        $this->assertFalse($this->isBacktick('"HELLO_WORLD"'));
    }

    public function testTranslateToBacktickField(): void
    {
        $this->assertSame('name', $this->translateToBacktickField('name'));
        $this->assertSame('`first name`', $this->translateToBacktickField('first name'));
        $this->assertSame('`first   name`', $this->translateToBacktickField('first   name'));
        $this->assertSame('`  first name  `', $this->translateToBacktickField('  first name  '));
        $this->assertSame('first_name', $this->translateToBacktickField('first_name'));
        $this->assertSame('first-name', $this->translateToBacktickField('first-name'));
    }

    public function testRemoveQuotes(): void
    {
        $this->assertSame('hello', $this->removeQuotes('"hello"'));
        $this->assertSame('hello', $this->removeQuotes("'hello'"));
        $this->assertSame('hello', $this->removeQuotes('`hello`'));
        $this->assertSame('123', $this->removeQuotes('"123"'));
        $this->assertSame('', $this->removeQuotes('""'));
    }

    public function testExtractPlainText(): void
    {
        $this->assertSame('hello world', $this->extractPlainText('Hello World'));
        $this->assertSame('text before text after', $this->extractPlainText('Text before `var_dump($x)` text after'));
        $this->assertSame('start end', $this->extractPlainText('Start ``SELECT * FROM users`` end'));
        $this->assertSame('hello world', $this->extractPlainText("Hello\n```php\necho 'test';\n```\nWorld"));
        $this->assertSame('hello world', $this->extractPlainText('<p>Hello <strong>World</strong></p>'));
        $this->assertSame('this is bold and italic and other.', $this->extractPlainText('This is **bold** and *italic* and _other_.'));
        $this->assertSame('visit openai now', $this->extractPlainText('Visit [OpenAI](https://openai.com) now'));
        $this->assertSame('image: !alt text done', $this->extractPlainText('Image: ![alt text](image.png) done'));
        $this->assertSame('title subtitle', $this->extractPlainText("# Title\n## Subtitle"));
        $this->assertSame('first second third', $this->extractPlainText("1. first\n2. second\n10. third"));
        $this->assertSame('before col2 after', $this->extractPlainText("Before\n| col1 | col2 |\nAfter"));
        $this->assertSame('before after', $this->extractPlainText("Before\n---\nAfter"));
        $this->assertSame('hello world!', $this->extractPlainText('Hello @#$%^&*() World!'));
        $this->assertSame('hello world test', $this->extractPlainText(" Hello \n\n   World \t test "));
    }

    public function testIsDateLike(): void
    {
        $this->assertTrue($this->isDateLike('2024-01-15'));
        $this->assertTrue($this->isDateLike('2024-01-15 12:30:00'));
        $this->assertTrue($this->isDateLike('next monday'));

        $this->assertFalse($this->isDateLike('not a date'));
        $this->assertFalse($this->isDateLike('1700000000'));
        $this->assertFalse($this->isDateLike(1700000000));
        $this->assertFalse($this->isDateLike(null));
        $this->assertFalse($this->isDateLike(''));
    }

    public function testHasSquareBracketsString(): void
    {
        $this->assertTrue($this->hasSquareBracketsString('abc [test] def'));
        $this->assertTrue($this->hasSquareBracketsString('abc [test'));
        $this->assertTrue($this->hasSquareBracketsString('abc test]'));

        $this->assertFalse($this->hasSquareBracketsString('abc `[test]` def'));
        $this->assertFalse($this->hasSquareBracketsString("abc '[test]' def"));
        $this->assertFalse($this->hasSquareBracketsString('abc "[test]" def'));

        $this->assertTrue($this->hasSquareBracketsString('abc "[test]" and [real]'));
        $this->assertFalse($this->hasSquareBracketsString('abc test def'));
    }
}
