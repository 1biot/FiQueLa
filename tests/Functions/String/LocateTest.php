<?php
namespace Functions\String;

use FQL\Functions\String\Locate;
use PHPUnit\Framework\TestCase;

class LocateTest extends TestCase
{
    public function testBasicLocate(): void
    {
        $locate = new Locate("'abc'", "'xyzabcdef'");
        $this->assertSame(4, $locate([], []));

        $locate = new Locate("'a'", "'banana'");
        $this->assertSame(2, $locate([], []));
    }

    public function testLocateWithPosition(): void
    {
        $locate = new Locate("a", "banana", 3);
        $this->assertSame(4, $locate([], [])); // First "a" after position 3 (1-based)

        $locate = new Locate("na", "banana", 3);
        $this->assertSame(3, $locate([], []));
    }

    public function testLocateNotFound(): void
    {
        $locate = new Locate("'x'", "'abc'");
        $this->assertSame(0, $locate([], []));
    }

    public function testLocateWithFieldFallback(): void
    {
        $locate = new Locate('abc', 'abcdef');
        $this->assertSame(1, $locate([], []));
    }

    public function testLocateWithInvalidTypes(): void
    {
        $locate = new Locate('field1', 'field2');

        $this->assertNull($locate(['field1' => ['a']], [])); // array
        $this->assertNull($locate(['field2' => ['text']], []));
    }

    public function testLocateBooleanAndNumericCoercion(): void
    {
        $locate = new Locate('1', '10101');
        $this->assertSame(1, $locate([], []));

        $locate = new Locate('true', 'truefalse');
        $this->assertSame(1, $locate([], []));

        $locate = new Locate('false', 'truefalse');
        $this->assertSame(5, $locate([], []));
    }

    public function testLocateWithUtf8Characters(): void
    {
        $locate = new Locate("'č'", "'říčany'");
        $this->assertSame(3, $locate([], []));
    }

    public function testLocateWithMultibyteSafety(): void
    {
        $locate = new Locate("'世'", "'你好，世界'");
        $this->assertSame(4, $locate([], []));
    }

    public function testLocatePositionBeyondLength(): void
    {
        $locate = new Locate("'a'", "'abc'", 10);
        $this->assertSame(0, $locate([], []));
    }
}
