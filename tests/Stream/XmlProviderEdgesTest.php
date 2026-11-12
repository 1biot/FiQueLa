<?php

namespace Stream;

use FQL\Stream\Xml;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the less-trodden paths of `XmlProvider::itemToArray` —
 * namespaces, attributes, mixed text+children, multi-sibling collapsing.
 */
class XmlProviderEdgesTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = (string) tempnam(sys_get_temp_dir(), 'fql-xml-edges-');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
    }

    public function testAttributesOnLeafElements(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<catalog>
    <product id="1" active="true">
        <name>Widget</name>
        <price currency="USD">42.00</price>
    </product>
</catalog>
XML;
        file_put_contents($this->tmp, $xml);
        $rows = iterator_to_array(Xml::open($this->tmp)->getStreamGenerator('catalog.product'), false);
        $this->assertCount(1, $rows);

        $row = $rows[0];
        // Attributes arrive under @attributes as raw strings.
        $this->assertSame('1', $row['@attributes']['id']);
        $this->assertSame('true', $row['@attributes']['active']);
    }

    public function testMultipleSiblingsCollapseIntoList(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<catalog>
    <product>
        <tag>a</tag>
        <tag>b</tag>
        <tag>c</tag>
    </product>
</catalog>
XML;
        file_put_contents($this->tmp, $xml);
        $rows = iterator_to_array(Xml::open($this->tmp)->getStreamGenerator('catalog.product'), false);
        $this->assertSame(['a', 'b', 'c'], $rows[0]['tag']);
    }

    public function testMixedContentProducesValueKey(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<catalog>
    <product id="1">Free text here</product>
</catalog>
XML;
        file_put_contents($this->tmp, $xml);
        $rows = iterator_to_array(Xml::open($this->tmp)->getStreamGenerator('catalog.product'), false);

        // Element has attributes + text → text surfaces as "value".
        $this->assertSame('Free text here', $rows[0]['value']);
        $this->assertSame('1', $rows[0]['@attributes']['id']);
    }

    public function testNamespacedAttributesAndChildren(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root xmlns:x="urn:example">
    <item x:id="NS-1">
        <x:label>Hello</x:label>
    </item>
</root>
XML;
        file_put_contents($this->tmp, $xml);
        $rows = iterator_to_array(Xml::open($this->tmp)->getStreamGenerator('root.item'), false);

        $this->assertCount(1, $rows);
        // Namespaced attribute key gets the `prefix:name` form.
        $this->assertArrayHasKey('x:id', $rows[0]['@attributes']);
        $this->assertSame('NS-1', $rows[0]['@attributes']['x:id']);
        // Namespaced children likewise.
        $this->assertArrayHasKey('x:label', $rows[0]);
    }

    public function testEmptyLeafElementReturnsEmptyString(): void
    {
        // `<a></a>` and `<a/>` both surface as the empty string — same shape
        // as a populated leaf element, just with no value. Pre-3.0.x the
        // empty form leaked as `[]`, which forced consumers to probe with
        // `IS ARRAY` / `is_array()` instead of the natural `IS NULL` / `= ''`.
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<catalog>
    <product></product>
    <product/>
    <product>Alice</product>
</catalog>
XML;
        file_put_contents($this->tmp, $xml);
        $rows = iterator_to_array(Xml::open($this->tmp)->getStreamGenerator('catalog.product'), false);
        $this->assertCount(3, $rows);
        $this->assertSame('', $rows[0]);
        $this->assertSame('', $rows[1]);
        $this->assertSame('Alice', $rows[2]);
    }

    public function testEmptyNestedLeafElementReturnsEmptyString(): void
    {
        // Nested empty leaf inside a populated parent: `<info><x/></info>`
        // becomes `['x' => '']`, not `['x' => []]`. Locks the IF/IS-ARRAY
        // user scenario — `info.invoiceNumber` is an empty string when the
        // XML element is absent of value, so `IS NULL` / `= ''` match
        // directly instead of needing a workaround.
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<items>
    <item><info><invoiceNumber/></info></item>
    <item><info><invoiceNumber>20260149</invoiceNumber></info></item>
</items>
XML;
        file_put_contents($this->tmp, $xml);
        $rows = iterator_to_array(Xml::open($this->tmp)->getStreamGenerator('items.item'), false);
        $this->assertCount(2, $rows);
        $this->assertSame(['info' => ['invoiceNumber' => '']], $rows[0]);
        $this->assertSame(['info' => ['invoiceNumber' => '20260149']], $rows[1]);
    }

    public function testEmptyElementWithAttributesKeepsAttributes(): void
    {
        // `<foo id="1"/>` — empty content but has attributes. The element
        // is no longer a pure leaf; we keep the @attributes structure as
        // before so consumers can still read them.
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<catalog>
    <product id="1"/>
</catalog>
XML;
        file_put_contents($this->tmp, $xml);
        $rows = iterator_to_array(Xml::open($this->tmp)->getStreamGenerator('catalog.product'), false);
        $this->assertCount(1, $rows);
        $this->assertSame(['@attributes' => ['id' => '1']], $rows[0]);
    }

    /**
     * @return array<int, mixed>
     */
    private function readAll(string $xml, ?string $query, ?string $encoding = null): array
    {
        file_put_contents($this->tmp, $xml);
        return iterator_to_array(Xml::openWithEncoding($this->tmp, $encoding)->getStreamGenerator($query));
    }

    public function testTextValuesCdataWhitespaceAndEntities(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<r>
  <i><id>001</id><c><![CDATA[<b>bold</b> & raw]]></c><m>pre <![CDATA[ cdata ]]> post</m><n> 0.50 </n><e>&#x41;&lt;&amp;</e></i>
  <i><ws xml:space="preserve">  keep  </ws><nl>
     line1
     line2
  </nl><mix>a<!-- c --> <b/>c</mix><pi>x<?target data?>y</pi></i>
  <i>
    <!-- comment -->
    <k>v</k>
  </i>
  <i><t>žluťoučký kůň €</t></i>
</r>
XML;
        $this->assertSame([
            0 => [
                'id' => '001',
                'c' => '<b>bold</b> & raw',
                'm' => 'pre  cdata  post',
                'n' => '0.50',
                'e' => 'A<&',
            ],
            1 => [
                // xml:* attributes keep their qualified name.
                'ws' => ['@attributes' => ['xml:space' => 'preserve'], 'value' => 'keep'],
                'nl' => "line1\n     line2",
                'mix' => ['b' => '', 'value' => 'a c'],
                'pi' => 'xy',
            ],
            2 => ['k' => 'v'],
            3 => ['t' => 'žluťoučký kůň €'],
        ], $this->readAll($xml, 'r.i'));
    }

    public function testTextNextToChildrenAndValueChildCollision(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<catalog>
  <product id="3"><a>x</a>text <b/> tail</product>
  <product>lead<value>child</value></product>
  <product><value>child</value></product>
  <product id="6" empty=""><value>child</value>outer<flag on="1"/></product>
</catalog>
XML;
        $this->assertSame([
            0 => ['@attributes' => ['id' => '3'], 'a' => 'x', 'b' => '', 'value' => 'text  tail'],
            1 => ['value' => 'lead'],
            2 => ['value' => 'child'],
            3 => [
                '@attributes' => ['id' => '6', 'empty' => ''],
                'value' => 'outer',
                'flag' => ['@attributes' => ['on' => '1']],
            ],
        ], $this->readAll($xml, 'catalog.product'));
    }

    public function testRepeatedSiblingsWithMixedShapes(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<r>
  <i><tag k="1">a</tag><tag>b</tag><tag k="3"/></i>
  <i><tag><x>1</x></tag><tag><x>2</x></tag><other/><tag>s</tag></i>
  <i><tag/><tag/></i>
  <i><g><x>1</x><x>2</x></g><g><x>3</x></g></i>
</r>
XML;
        $this->assertSame([
            0 => ['tag' => [
                ['@attributes' => ['k' => '1'], 'value' => 'a'],
                'b',
                ['@attributes' => ['k' => '3']],
            ]],
            1 => ['tag' => [['x' => '1'], ['x' => '2'], 's'], 'other' => ''],
            2 => ['tag' => ['', '']],
            3 => ['g' => [['x' => ['1', '2']], ['x' => '3']]],
        ], $this->readAll($xml, 'r.i'));
    }

    public function testAdjacentRecordsAreNotSkipped(): void
    {
        $xml = '<?xml version="1.0"?><r><i><a>1</a></i><i/><i><a>2</a></i><i id="x"/><i>t</i></r>';
        $this->assertSame(
            [['a' => '1'], '', ['a' => '2'], ['@attributes' => ['id' => 'x']], 't'],
            $this->readAll($xml, 'r.i')
        );
    }

    public function testPathSelectionByDepthAndSegmentName(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<SHOP>
  <SHOPITEM><SHOPITEM>inner</SHOPITEM><id>1</id></SHOPITEM>
  <OTHER><id>2</id></OTHER>
  <SHOP><id>3</id></SHOP>
  <SHOPITEM><deep><SHOPITEM><id>9</id></SHOPITEM></deep></SHOPITEM>
</SHOP>
XML;
        // Any element at the query depth whose name is one of the path segments.
        $this->assertSame([
            ['SHOPITEM' => 'inner', 'id' => '1'],
            ['id' => '3'],
            ['deep' => ['SHOPITEM' => ['id' => '9']]],
        ], $this->readAll($xml, 'SHOP.SHOPITEM'));

        $this->assertSame(['inner', '1', '2', '3'], $this->readAll($xml, 'SHOP.SHOPITEM.id'));
        $this->assertCount(1, $this->readAll($xml, '*'));
        $this->assertSame([], $this->readAll($xml, ''));
    }

    public function testDefaultNamespaceChildrenAreNotDuplicated(): void
    {
        // Elements in the default namespace keep their plain names, once.
        $xml = <<<XML
<?xml version="1.0"?>
<feed xmlns="urn:atom"><entry id="1"><title>T</title><author><name>A</name></author></entry></feed>
XML;
        $this->assertSame(
            [['@attributes' => ['id' => '1'], 'title' => 'T', 'author' => ['name' => 'A']]],
            $this->readAll($xml, 'feed.entry')
        );
    }

    public function testPrefixedChildrenKeepDocumentOrder(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<rss xmlns:g="urn:g"><item><g:id>1</g:id><title>T</title><g:price>10</g:price><link>L</link><g:id>2</g:id></item></rss>
XML;
        $rows = $this->readAll($xml, 'rss.item');
        $this->assertSame([['g:id' => ['1', '2'], 'title' => 'T', 'g:price' => '10', 'link' => 'L']], $rows);
        $this->assertSame(['g:id', 'title', 'g:price', 'link'], array_keys($rows[0]));
    }

    public function testPrefixesSharingNamespaceUriStaySeparate(): void
    {
        $xml = '<?xml version="1.0"?><r xmlns:a="urn:x" xmlns:b="urn:x"><i><a:n>1</a:n><b:n>2</b:n></i></r>';
        $this->assertSame([['a:n' => '1', 'b:n' => '2']], $this->readAll($xml, 'r.i'));
    }

    public function testNamespaceDeclarationsAreNotAttributes(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root>
  <item><id>1</id></item>
  <item xmlns:y="urn:y" xmlns="urn:d" y:k="v" xml:lang="cs"><id>3</id><y:z a="1">zz</y:z></item><item/>
  <item><id>4</id></item>
</root>
XML;
        $this->assertSame([
            0 => ['id' => '1'],
            1 => [
                '@attributes' => ['y:k' => 'v', 'xml:lang' => 'cs'],
                'id' => '3',
                'y:z' => ['@attributes' => ['a' => '1'], 'value' => 'zz'],
            ],
            2 => '',
            3 => ['id' => '4'],
        ], $this->readAll($xml, 'root.item'));
    }

    public function testNamespacedRecordElementIsSelectedByLocalName(): void
    {
        $xml = '<?xml version="1.0"?><s:SHOP xmlns:s="urn:s"><s:ITEM><s:id>1</s:id></s:ITEM></s:SHOP>';
        $this->assertSame([['s:id' => '1']], $this->readAll($xml, 'SHOP.ITEM'));
    }

    /**
     * @return array<int, mixed>
     */
    private function readAllSilently(string $xml, string $query, int &$count): array
    {
        file_put_contents($this->tmp, $xml);
        $rows = [];
        set_error_handler(static fn (): bool => true);
        try {
            foreach (Xml::open($this->tmp)->getStreamGenerator($query) as $row) {
                $rows[] = $row;
                $count++;
            }
        } finally {
            restore_error_handler();
        }
        return $rows;
    }

    public function testInternalEntitiesAreExpanded(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE r [<!ENTITY co "Company &amp; Co"><!ENTITY mk "<b>x</b>">]>
<r><i><n>plain</n></i><i><n>&co;</n><m>&mk;</m></i></r>
XML;
        $this->assertSame(
            [['n' => 'plain'], ['n' => 'Company & Co', 'm' => ['b' => 'x']]],
            $this->readAll($xml, 'r.i')
        );
    }

    public function testExternalEntitiesAreNotLoaded(): void
    {
        $secret = (string) tempnam(sys_get_temp_dir(), 'fql-xml-secret-');
        file_put_contents($secret, 'SECRET');
        $xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY ext SYSTEM "file://' . $secret . '">]>'
            . '<r><i><n>plain</n></i><i><n>&ext;</n></i></r>';

        $count = 0;
        try {
            $rows = $this->readAllSilently($xml, 'r.i', $count);
            $this->fail('Expected UnexpectedValueException, got ' . json_encode($rows));
        } catch (\FQL\Exception\UnexpectedValueException $e) {
            $this->assertStringContainsString('External XML entities are not supported', $e->getMessage());
        } finally {
            @unlink($secret);
        }
        // libxml parses ahead, so the entity may be refused before the first record is yielded.
        $this->assertLessThanOrEqual(1, $count);
    }

    public function testCallerEntityLoaderIsRestoredBetweenRecords(): void
    {
        $loader = static fn (): mixed => null;
        libxml_set_external_entity_loader($loader);
        try {
            file_put_contents($this->tmp, '<?xml version="1.0"?><r><i><a>1</a></i><i><a>2</a></i></r>');
            foreach (Xml::open($this->tmp)->getStreamGenerator('r.i') as $row) {
                $this->assertSame($loader, libxml_get_external_entity_loader());
                break;
            }
            $this->assertSame($loader, libxml_get_external_entity_loader());
        } finally {
            libxml_set_external_entity_loader(null);
        }
        $this->assertNull(libxml_get_external_entity_loader());
    }

    public function testInputEncodingIsConvertedToUtf8(): void
    {
        $declared = "<?xml version=\"1.0\" encoding=\"ISO-8859-2\"?>\n<r><i><t>\xbelu\xbbou\xe8k\xfd</t></i></r>";
        $this->assertSame([['t' => 'žluťoučký']], $this->readAll($declared, 'r.i'));

        $undeclared = "<r><i><t>\x9elu\x9dou\xe8k\xfd</t></i></r>";
        $this->assertSame([['t' => 'žluťoučký']], $this->readAll($undeclared, 'r.i', 'windows-1250'));
    }

    public function testMalformedRecordThrows(): void
    {
        $xml = '<?xml version="1.0"?>' . "\n<r>\n" . str_repeat("<i><a>1</a></i>\n", 20000) . '<i><a>2</b></i></r>';
        $count = 0;
        try {
            $this->readAllSilently($xml, 'r.i', $count);
            $this->fail('Expected UnexpectedValueException');
        } catch (\FQL\Exception\UnexpectedValueException $e) {
            $this->assertSame('Unable to parse XML record: unexpected end of document.', $e->getMessage());
        }
        $this->assertGreaterThan(0, $count);
        $this->assertLessThanOrEqual(20000, $count);
    }

    public function testEarlyTerminationReleasesReader(): void
    {
        file_put_contents($this->tmp, '<?xml version="1.0"?><r><i><a>1</a></i><i><a>2</a></i></r>');
        $stream = Xml::open($this->tmp);
        for ($i = 0; $i < 2000; $i++) {
            foreach ($stream->getStreamGenerator('r.i') as $row) {
                $this->assertSame(['a' => '1'], $row);
                break;
            }
        }
        // Each abandoned generator must close its reader; a leak would run
        // out of file descriptors long before 2000 iterations.
        $this->assertSame([['a' => '1'], ['a' => '2']], iterator_to_array($stream->getStreamGenerator('r.i')));
    }
}
