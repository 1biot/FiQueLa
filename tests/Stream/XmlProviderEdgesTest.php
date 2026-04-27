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
}
