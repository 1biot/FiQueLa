<?php

namespace Exception;

use FQL\Exception\FileAlreadyExistsException;
use FQL\Exception\FileNotFoundException;
use FQL\Exception\InvalidFormatException;
use FQL\Exception\PathNotFoundException;
use FQL\Exception\PathOutsideBaseException;
use FQL\Exception\QueryLogicException;
use FQL\Exception\SelectException;
use FQL\Exception\SourceAwareException;
use FQL\Exception\UnknownFunctionException;
use FQL\Query\FileQuery;
use FQL\Sql\Compiler;
use FQL\Sql\Parser\ParseException;
use FQL\Sql\Token\Position;
use FQL\Sql\Token\Span;
use FQL\Sql\Token\Token;
use FQL\Sql\Token\TokenType;
use FQL\Stream\Writers\WriterFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Errors tied to the query text carry a rule, a span into the parsed SQL, params and a
 * reason; their class and message stay as before 3.3.0.
 */
class SourceAwareExceptionTest extends TestCase
{
    private const PRODUCTS = 'json(examples/data/products.json).data.products';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/fql-source-aware-' . uniqid();
        mkdir($this->dir . '/project/data', 0775, true);
        file_put_contents($this->dir . '/project/data/a.json', '[{"id": 1}]');
        file_put_contents($this->dir . '/outside.json', '[{"id": 1}]');
    }

    protected function tearDown(): void
    {
        foreach (['/project/data/a.json', '/outside.json'] as $file) {
            @unlink($this->dir . $file);
        }
        @rmdir($this->dir . '/project/data');
        @rmdir($this->dir . '/project');
        @rmdir($this->dir);
    }

    public function testEveryLibraryExceptionIsSourceAware(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/src/Exception/*.php') ?: [] as $file) {
            $class = 'FQL\\Exception\\' . basename($file, '.php');
            $reflection = new \ReflectionClass($class);
            if ($reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }
            $this->assertTrue($reflection->implementsInterface(SourceAwareException::class), $class);
        }
        $this->assertTrue(is_a(ParseException::class, SourceAwareException::class, true));
    }

    /**
     * @return array<string, array{string, string, string, array<string, mixed>}>
     */
    public static function syntaxErrors(): array
    {
        return [
            'unexpected token' => [
                'SELECT a b FROM x',
                'b',
                'Unexpected IDENTIFIER ("b") in SELECT clause (Expected comma between SELECT expressions)',
                ['kind' => 'unexpected-token', 'token' => 'b', 'tokenType' => 'IDENTIFIER', 'expected' => []],
            ],
            'expected token at the end' => [
                'SELECT a FROM json(a.json) WHERE a =',
                '',
                'Unexpected EOF ("") in expression',
                ['kind' => 'unexpected-token', 'tokenType' => 'EOF'],
            ],
            'unterminated string' => [
                'SELECT a FROM x WHERE a = "abc',
                '"',
                'Unterminated string literal',
                ['kind' => 'unterminated-string', 'token' => '"'],
            ],
            'unterminated comment' => [
                "SELECT a /* note\nFROM x",
                '/*',
                'Unterminated block comment',
                ['kind' => 'unterminated-comment', 'token' => '/*'],
            ],
            'unterminated backtick' => [
                'SELECT `a FROM x',
                '`',
                'Unterminated backtick identifier',
                ['kind' => 'unterminated-identifier'],
            ],
            'unexpected operator' => [
                'SELECT a ! b FROM x',
                '!',
                'Unexpected operator character "!"',
                ['kind' => 'unexpected-operator', 'token' => '!'],
            ],
            'unexpected multibyte character' => [
                'SELECT a – b FROM x',
                '–',
                'Unexpected character "–"',
                ['kind' => 'unexpected-character', 'token' => '–'],
            ],
            'character after an identifier byte' => [
                'SELECT ř FROM x',
                'ř',
                'Unexpected character "ř"',
                ['kind' => 'unexpected-character', 'token' => 'ř'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('syntaxErrors')]
    public function testParseExceptionDescribesTheOffendingToken(
        string $sql,
        string $text,
        string $reason,
        array $params
    ): void {
        $exception = $this->parseError($sql);

        $this->assertSame('syntax-error', $exception->getRule());
        $this->assertSame($reason, $exception->getReason());
        $this->assertSame($text, $exception->getSpan()?->slice($sql));
        $this->assertSame($params, array_intersect_key($exception->getParams(), $params));
        $this->assertSame($exception->token->position->offset, $exception->getSpan()?->start);
        $this->assertStringStartsWith($reason, $exception->getMessage());
        $this->assertMatchesRegularExpression('/at line \d+, column \d+$/', $exception->getMessage());
    }

    public function testParseExceptionMessageIsUnchanged(): void
    {
        $exception = $this->parseError("SELECT a\nFROM json(a.json) WHERE a = ");

        $this->assertSame('Unexpected EOF ("") in expression at line 2, column 29', $exception->getMessage());
        $this->assertSame(['context' => 'expression'], array_intersect_key($exception->getParams(), ['context' => 1]));
    }

    public function testParseExceptionWithASyntheticTokenHasNoSpan(): void
    {
        $exception = new ParseException(
            new Token(TokenType::EOF, '', '', Position::synthetic(), 0),
            [TokenType::IDENTIFIER],
            'Expected IDENTIFIER at line 0, column 0'
        );

        $this->assertNull($exception->getSpan());
        $this->assertSame('Expected IDENTIFIER', $exception->getReason());
        $this->assertSame(['IDENTIFIER'], $exception->getParams()['expected']);
    }

    public function testAttachedSpanWinsOverTheTokenAndIsNotReplaced(): void
    {
        $exception = $this->parseError('SELECT a b FROM x');

        $exception->attachSpan(new Span(0, 6));
        $exception->attachSpan(new Span(7, 8));

        $this->assertEquals(new Span(0, 6), $exception->getSpan());
    }

    public function testUnknownFunctionKeepsTheListOutOfTheReason(): void
    {
        $exception = UnknownFunctionException::create('FOO', ['UPPER', 'LOWER']);

        $this->assertSame('Unknown FQL function "FOO". Registered functions: LOWER, UPPER', $exception->getMessage());
        $this->assertSame('Unknown FQL function "FOO"', $exception->getReason());
        $this->assertSame('unknown-function', $exception->getRule());
        $this->assertSame(['function' => 'FOO'], $exception->getParams());
        $this->assertSame(['LOWER', 'UPPER'], $exception->getRegisteredFunctions());
        $this->assertNull($exception->getSpan());
    }

    public function testUnknownFunctionAtExecutionHasNoSpan(): void
    {
        $query = (new Compiler('SELECT FOO(name) FROM ' . self::PRODUCTS))->toQuery();
        try {
            iterator_to_array($query->execute()->getIterator());
            $this->fail('Expected UnknownFunctionException');
        } catch (UnknownFunctionException $exception) {
            $this->assertSame(['function' => 'FOO'], $exception->getParams());
            $this->assertNull($exception->getSpan());
        }
    }

    public function testFileExceptionsCarryThePath(): void
    {
        $exists = FileAlreadyExistsException::forPath('/tmp/out.csv');
        $this->assertSame('File "/tmp/out.csv" already exists', $exists->getMessage());
        $this->assertSame(['file-exists', ['path' => '/tmp/out.csv']], [$exists->getRule(), $exists->getParams()]);

        $notFound = FileNotFoundException::forPath('./a.json');
        $this->assertSame('File not found or not readable.', $notFound->getMessage());
        $this->assertSame(['file-not-found', ['path' => './a.json']], [$notFound->getRule(), $notFound->getParams()]);

        $plain = new FileNotFoundException('Gone');
        $this->assertSame(['file-not-found', [], 'Gone'], [$plain->getRule(), $plain->getParams(), $plain->getReason()]);
    }

    public function testWriterRefusesAnExistingFileWithItsPath(): void
    {
        $file = $this->dir . '/project/data/a.json';
        try {
            WriterFactory::create(new FileQuery('json(' . $file . ')'));
            $this->fail('Expected FileAlreadyExistsException');
        } catch (FileAlreadyExistsException $exception) {
            $this->assertSame(['path' => $file], $exception->getParams());
        }
    }

    /**
     * @return array<string, array{string, bool, class-string, string, ?string, array<string, string>}>
     */
    public static function buildErrors(): array
    {
        $products = self::PRODUCTS;
        return [
            'missing file' => [
                'SELECT * FROM json(./nope.json)', false,
                FileNotFoundException::class, 'json(./nope.json)', 'file-not-found', ['path' => './nope.json'],
            ],
            'missing file under the base path' => [
                'SELECT * FROM json(data/nope.json)', true,
                PathNotFoundException::class, 'json(data/nope.json)', 'file-not-found', ['path' => 'data/nope.json'],
            ],
            'file outside the base path' => [
                "SELECT *\nFROM json(../outside.json)", true,
                PathOutsideBaseException::class, 'json(../outside.json)', 'file-outside-base',
                ['path' => '../outside.json'],
            ],
            'missing file outside the base path' => [
                'SELECT * FROM json(../../nope.json)', true,
                PathOutsideBaseException::class, 'json(../../nope.json)', 'file-outside-base',
                ['path' => '../../nope.json'],
            ],
            'INTO outside the base path' => [
                'SELECT id FROM json(data/a.json) INTO json(../out.json)', true,
                PathOutsideBaseException::class, 'json(../out.json)', 'file-outside-base', ['path' => '../out.json'],
            ],
            'missing JOIN source' => [
                "SELECT a.name FROM $products AS a JOIN json(./nope.json) AS b ON a.id = b.id", false,
                FileNotFoundException::class, 'json(./nope.json)', 'file-not-found', ['path' => './nope.json'],
            ],
            'missing file in a CTE body' => [
                'WITH t AS (SELECT * FROM json(./nope.json)) SELECT * FROM t', false,
                FileNotFoundException::class, 'json(./nope.json)', 'file-not-found', ['path' => './nope.json'],
            ],
            'missing file in a subquery' => [
                'SELECT * FROM (SELECT * FROM json(./nope.json)) AS s', false,
                FileNotFoundException::class, 'json(./nope.json)', 'file-not-found', ['path' => './nope.json'],
            ],
            'duplicate field' => [
                "SELECT name AS x,\n  price AS x FROM $products", false,
                SelectException::class, 'price AS x', null, [],
            ],
            'FROM without a file' => [
                'SELECT * FROM nope', false,
                QueryLogicException::class, 'nope', null, [],
            ],
            'unsupported JOIN condition' => [
                "SELECT a.name FROM $products AS a JOIN $products AS b ON a.id IN (1, 2)", false,
                QueryLogicException::class, "JOIN $products AS b ON a.id IN (1, 2)", null, [],
            ],
            'missing FROM falls back to the statement' => [
                'SELECT 1', false,
                QueryLogicException::class, 'SELECT 1', null, [],
            ],
        ];
    }

    /**
     * @param class-string $class
     * @param array<string, string> $params
     */
    #[DataProvider('buildErrors')]
    public function testBuildErrorsPointAtTheNodeBeingBuilt(
        string $sql,
        bool $withBasePath,
        string $class,
        string $text,
        ?string $rule,
        array $params
    ): void {
        $basePath = $withBasePath ? $this->dir . '/project' : null;
        try {
            (new Compiler($sql, $basePath))->toQuery();
            $this->fail('Expected ' . $class);
        } catch (SourceAwareException $exception) {
            $this->assertSame($class, $exception::class);
            $this->assertSame($text, $exception->getSpan()?->slice($sql));
            $this->assertSame($rule, $exception->getRule());
            $this->assertSame($params, array_intersect_key($exception->getParams(), $params));
        }
    }

    public function testPathErrorsKeepTheirLegacyClassAndMessage(): void
    {
        foreach (['json(data/nope.json)', 'json(../outside.json)'] as $source) {
            try {
                (new Compiler('SELECT * FROM ' . $source, $this->dir . '/project'))->toQuery();
                $this->fail('Expected InvalidFormatException');
            } catch (InvalidFormatException $exception) {
                $this->assertSame('Invalid path of file', $exception->getMessage());
                $this->assertSame($this->dir . '/project', $exception->getParams()['basePath']);
                $this->assertStringContainsString('"' . substr($source, 5, -1) . '"', $exception->getReason());
            }
        }
    }

    private function parseError(string $sql): ParseException
    {
        try {
            (new Compiler($sql))->toAst();
        } catch (ParseException $exception) {
            return $exception;
        }
        $this->fail('Expected ParseException for ' . $sql);
    }
}
