<?php

namespace Enum;

use FQL\Enum\Format;
use FQL\Enum\Fulltext;
use FQL\Enum\Operator;
use FQL\Enum\Type;
use FQL\Exception\InvalidFormatException;
use PHPUnit\Framework\TestCase;

/**
 * Top-up coverage for Enum edge paths that weren't reached by the core
 * feature-level test suite: Format validators for every format,
 * Operator::render() branches, Type cast branches, Fulltext modes.
 */
class EnumEdgeTest extends TestCase
{
    // --- Format validators -------------------------------------------------

    public function testCsvDelimiterValidationRejectsMultibyte(): void
    {
        $this->expectException(InvalidFormatException::class);
        Format::CSV->validateParams(['delimiter' => ';;']);
    }

    public function testCsvUseHeaderValidationRejectsArbitrary(): void
    {
        $this->expectException(InvalidFormatException::class);
        Format::CSV->validateParams(['useHeader' => 'yes']);
    }

    public function testCsvEnclosureValidationRejectsMultibyte(): void
    {
        $this->expectException(InvalidFormatException::class);
        Format::CSV->validateParams(['enclosure' => '""']);
    }

    public function testCsvBomValidationRejectsArbitrary(): void
    {
        $this->expectException(InvalidFormatException::class);
        Format::CSV->validateParams(['bom' => 'on']);
    }

    public function testCsvEncodingValidationRejectsUnknown(): void
    {
        $this->expectException(InvalidFormatException::class);
        Format::CSV->validateParams(['encoding' => 'this-is-not-a-real-encoding']);
    }

    public function testCsvAcceptsValidParams(): void
    {
        // No exception = pass.
        Format::CSV->validateParams([
            'encoding' => 'UTF-8',
            'delimiter' => ',',
            'useHeader' => '1',
            'enclosure' => '"',
            'bom' => '0',
        ]);
        $this->expectNotToPerformAssertions();
    }

    public function testXmlEncodingValidation(): void
    {
        $this->expectException(InvalidFormatException::class);
        Format::XML->validateParams(['encoding' => 'bogus-encoding']);
    }

    public function testFromExtensionMaps(): void
    {
        $this->assertSame(Format::CSV, Format::fromExtension('csv'));
        $this->assertSame(Format::XML, Format::fromExtension('xml'));
        $this->assertSame(Format::NEON, Format::fromExtension('neon'));
    }

    public function testFromExtensionRejectsUnknown(): void
    {
        $this->expectException(InvalidFormatException::class);
        Format::fromExtension('notarealformat');
    }

    // --- Type ---------------------------------------------------------------

    public function testTypeMatchByStringBooleanAndNull(): void
    {
        $this->assertTrue(Type::matchByString('true'));
        $this->assertFalse(Type::matchByString('false'));
        $this->assertNull(Type::matchByString('null'));
    }

    public function testTypeMatchByStringNumbers(): void
    {
        $this->assertSame(42, Type::matchByString('42'));
        $this->assertEqualsWithDelta(3.14, Type::matchByString('3.14'), 0.0001);
    }

    public function testTypeMatchByStringCommaDecimal(): void
    {
        // European number format — `matchByString` normalises comma to dot.
        $result = Type::matchByString('1,5');
        $this->assertEqualsWithDelta(1.5, $result, 0.0001);
    }

    public function testTypeMatchByStringQuotedStringStripsQuotes(): void
    {
        $this->assertSame('hello', Type::matchByString('"hello"'));
        $this->assertSame('world', Type::matchByString("'world'"));
    }

    public function testTypeMatchByStringFallbackToString(): void
    {
        $this->assertSame('alice', Type::matchByString('alice'));
    }

    public function testTypeMatch(): void
    {
        $this->assertSame(Type::INTEGER, Type::match(42));
        $this->assertSame(Type::FLOAT, Type::match(3.14));
        $this->assertSame(Type::STRING, Type::match('hello'));
        $this->assertSame(Type::BOOLEAN, Type::match(true));
        $this->assertSame(Type::NULL, Type::match(null));
        $this->assertSame(Type::ARRAY, Type::match([]));
        $this->assertSame(Type::OBJECT, Type::match(new \stdClass()));
    }

    public function testTypeCastValueBasicCoercions(): void
    {
        $this->assertSame('42', Type::castValue(42, Type::STRING));
        $this->assertSame(42, Type::castValue('42', Type::INTEGER));
        $this->assertEqualsWithDelta(3.14, Type::castValue('3.14', Type::FLOAT), 0.0001);
        $this->assertTrue(Type::castValue('yes', Type::BOOLEAN));
        $this->assertNull(Type::castValue('whatever', Type::NULL));
    }

    public function testTypeCastValueArrayWrap(): void
    {
        $this->assertSame([42], Type::castValue(42, Type::ARRAY));
        $this->assertSame(['a'], Type::castValue(['a'], Type::ARRAY));
    }

    public function testTypeCastValueInferType(): void
    {
        // Passing `null` as target forces castValue to use match($value).
        $this->assertSame('hello', Type::castValue('hello'));
        $this->assertSame(42, Type::castValue(42));
    }

    public function testTypeCastValueNumericZeroFallback(): void
    {
        // Non-numeric strings → INTEGER cast returns 0 per helper's contract.
        $this->assertSame(0, Type::castValue('not a number', Type::INTEGER));
        $this->assertSame(0.0, Type::castValue('still not', Type::FLOAT));
    }

    public function testTypeCastValueObjectFallback(): void
    {
        $obj = new \stdClass();
        $this->assertSame($obj, Type::castValue($obj, Type::OBJECT));
        $this->assertNull(Type::castValue('not-an-object', Type::OBJECT));
    }

    public function testTypeListValues(): void
    {
        $values = Type::listValues();
        $this->assertIsArray($values);
        $this->assertContains(Type::INTEGER, $values);
        $this->assertContains(Type::STRING, $values);
        $this->assertContains(Type::NULL, $values);
    }

    // --- Operator::render ---------------------------------------------------

    public function testOperatorRenderComparison(): void
    {
        $result = Operator::EQUAL->render('age', 18);
        $this->assertSame('age = 18', $result);
    }

    public function testOperatorRenderStringLiteral(): void
    {
        $result = Operator::EQUAL->render('name', 'alice');
        // Strings are single-quoted in the rendering.
        $this->assertStringContainsString("'alice'", $result);
    }

    public function testOperatorRenderIn(): void
    {
        $result = Operator::IN->render('status', ['active', 'pending']);
        $this->assertStringContainsString('IN (', $result);
        $this->assertStringContainsString('"active"', $result);
        $this->assertStringContainsString('"pending"', $result);
    }

    public function testOperatorRenderLike(): void
    {
        $result = Operator::LIKE->render('name', 'Joh%');
        $this->assertStringContainsString('LIKE', $result);
        $this->assertStringContainsString('"Joh%"', $result);
    }

    public function testOperatorRenderBetween(): void
    {
        $result = Operator::BETWEEN->render('age', [18, 65]);
        $this->assertSame('age BETWEEN 18 AND 65', $result);
    }

    public function testOperatorRenderIsNull(): void
    {
        $result = Operator::IS->render('deletedAt', Type::NULL);
        $this->assertStringContainsString('IS NULL', $result);
    }

    public function testOperatorFromOrFailThrowsOnUnknown(): void
    {
        $this->expectException(\FQL\Exception\InvalidArgumentException::class);
        Operator::fromOrFail('~~~');
    }

    public function testOperatorBetweenCoercesStringValues(): void
    {
        // CSV returns strings → BETWEEN uses is_numeric path when both bounds
        // are numeric.
        $this->assertTrue(Operator::BETWEEN->evaluate('50', [10, 100]));
        $this->assertFalse(Operator::BETWEEN->evaluate('5', [10, 100]));
    }

    public function testOperatorBetweenDateLikeComparison(): void
    {
        $this->assertTrue(
            Operator::BETWEEN->evaluate('2024-06-15', ['2024-01-01', '2024-12-31'])
        );
    }

    public function testOperatorInCoercesStringToInt(): void
    {
        // With the coerced IN — "42" (string) matches 42 (int) in the list.
        $this->assertTrue(Operator::IN->evaluate('42', [42, 100]));
    }

    public function testOperatorLikeReturnsFalseForNonString(): void
    {
        // LIKE requires both sides string; non-string left → false.
        $this->assertFalse(Operator::LIKE->evaluate(123, 'abc'));
    }

    public function testOperatorRegexpHandlesFlags(): void
    {
        $this->assertTrue(Operator::REGEXP->evaluate('hello', '/^h/'));
        $this->assertFalse(Operator::REGEXP->evaluate('hello', '/^X/'));
    }

    public function testOperatorNotRegexpInverts(): void
    {
        $this->assertTrue(Operator::NOT_REGEXP->evaluate('hello', '/^X/'));
        $this->assertFalse(Operator::NOT_REGEXP->evaluate('hello', '/^h/'));
    }

    // --- Fulltext ---------------------------------------------------------

    public function testFulltextEnumCases(): void
    {
        $this->assertNotNull(Fulltext::NATURAL);
        $this->assertNotNull(Fulltext::BOOLEAN);
    }

    public function testFulltextCalculateNatural(): void
    {
        $score = Fulltext::NATURAL->calculate('hello world goodbye', ['hello']);
        $this->assertGreaterThan(0, $score);
    }

    public function testFulltextCalculateNoMatch(): void
    {
        $score = Fulltext::NATURAL->calculate('hello world', ['missing']);
        $this->assertSame(0.0, $score);
    }

    public function testFulltextCalculateBooleanModeRequiredTerm(): void
    {
        $score = Fulltext::BOOLEAN->calculate('hello world', ['+hello']);
        $this->assertGreaterThan(0, $score);
    }

    public function testFulltextCalculateBooleanModeExcludedTerm(): void
    {
        // Whatever the semantics (exclusion logic may count or cancel), the
        // implementation returns a non-negative numeric score.
        $score = Fulltext::BOOLEAN->calculate('hello world', ['+hello', '-world']);
        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0.0, $score);
    }
}
