<?php

namespace FQL\Stream;

use FQL\Exception;
use FQL\Interface;
use Traversable;

/**
 * @phpstan-import-type StreamProviderArrayIterator from ArrayStreamProvider
 * @phpstan-import-type StreamProviderArrayIteratorValue from ArrayStreamProvider
 * @implements \IteratorAggregate<StreamProviderArrayIteratorValue>
 */
abstract class XmlProvider extends AbstractStream implements \IteratorAggregate
{
    private ?string $inputEncoding = null;

    protected function __construct(private readonly string $xmlFilePath)
    {
    }

    public function setInputEncoding(?string $encoding): self
    {
        $this->inputEncoding = $encoding;
        return $this;
    }

    public function getIterator(): Traversable
    {
        return $this->getStream('');
    }

    /**
     * @param string|null $query
     * @return StreamProviderArrayIterator
     * @throws Exception\UnableOpenFileException
     */
    public function getStream(?string $query): \ArrayIterator
    {
        return new \ArrayIterator(iterator_to_array($this->getStreamGenerator($query)));
    }

    /**
     * @throws Exception\UnexpectedValueException
     * @throws Exception\UnableOpenFileException
     */
    public function getStreamGenerator(?string $query): \Generator
    {
        $query = $query ?? Interface\Query::FROM_ALL;

        $xmlReader = \XMLReader::open($this->xmlFilePath, $this->inputEncoding);
        if (!$xmlReader) {
            throw new Exception\UnableOpenFileException('Unable to open XML file.');
        }

        $depth = substr_count($query, '.');
        while ($xmlReader->read()) {
            if ($this->isReadValid($xmlReader, $query, $depth)) {
                try {
                    yield $this->itemToArray(
                        new \SimpleXMLElement($xmlReader->readOuterXml(), LIBXML_NOCDATA)
                    );
                } catch (\Exception $e) {
                    throw new Exception\UnexpectedValueException($e->getMessage());
                }
            }
        }
        $xmlReader->close();
    }

    private function isReadValid(\XMLReader $xmlReader, string $query, int $depth): bool
    {
        return $xmlReader->nodeType == \XMLReader::ELEMENT
            && (
                $query !== ''
                && in_array($xmlReader->localName, explode('.', $query))
                || $query === Interface\Query::FROM_ALL
            ) && $xmlReader->depth === $depth;
    }

    /**
     * Converts a `SimpleXMLElement` to its array representation. Leaves every
     * textual value (attribute, child text, leaf value) as a raw string —
     * downstream coercion happens lazily in `Enum\Operator::evaluate()` when
     * the value is compared or in `Sql\Runtime\ExpressionEvaluator` when it
     * enters an arithmetic / CAST expression. Previously this method invoked
     * `Type::matchByString()` per attribute and per text node, which on real
     * XML feeds (tens of thousands of rows, many attributes each) burned
     * seconds in regex + `is_numeric` probes with zero benefit when the
     * query only touched a handful of fields.
     *
     * @param \SimpleXMLElement $element
     * @return string|StreamProviderArrayIteratorValue
     */
    private function itemToArray(\SimpleXMLElement $element): string|array
    {
        $result = [];

        // Attributes — cast to string (SimpleXMLElement wraps them), no typing.
        foreach ($element->attributes() as $attributeName => $attributeValue) {
            $result['@attributes'][$attributeName] = (string) $attributeValue;
        }

        // Attributes with namespaces — same treatment.
        foreach ($element->getNamespaces(true) as $prefix => $namespace) {
            foreach ($element->attributes($namespace) as $attributeName => $attributeValue) {
                $key = $prefix ? "{$prefix}:{$attributeName}" : $attributeName;
                $result['@attributes'][$key] = (string) $attributeValue;
            }
        }

        // Child elements — recurse; leaf children return a plain string which
        // is stored as-is (no `matchByString`).
        foreach ($element->children() as $childName => $childElement) {
            $childArray = $this->itemToArray($childElement);
            if (isset($result[$childName])) {
                if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
                    $result[$childName] = [$result[$childName]];
                }
                $result[$childName][] = $childArray;
            } else {
                $result[$childName] = $childArray;
            }
        }

        // Child elements with namespaces.
        foreach ($element->getNamespaces(true) as $prefix => $namespace) {
            foreach ($element->children($namespace) as $childName => $childElement) {
                $key = $prefix ? "{$prefix}:{$childName}" : $childName;
                $childArray = $this->itemToArray($childElement);
                if (isset($result[$key])) {
                    if (!is_array($result[$key]) || !isset($result[$key][0])) {
                        $result[$key] = [$result[$key]];
                    }
                    $result[$key][] = $childArray;
                } else {
                    $result[$key] = $childArray;
                }
            }
        }

        // Leaf element with only a text value — return the raw string.
        $value = trim((string) $element);
        if ($value !== '' && empty($result)) {
            return $value;
        }

        // Mixed content (children/attributes + text) — record the text under
        // the sentinel key `value` as a raw string.
        if ($value !== '') {
            $result['value'] = $value;
        }

        return $result;
    }

    public function getXmlFilePath(): string
    {
        return $this->xmlFilePath;
    }

    public function getInputEncoding(): ?string
    {
        return $this->inputEncoding;
    }

    public function provideSource(): string
    {
        $params = [];
        if ($this->xmlFilePath !== '') {
            $params[] = basename($this->xmlFilePath);
        }

        if ($this->inputEncoding !== null && strtolower($this->inputEncoding) !== 'utf-8') {
            $params[] = sprintf('"%s"', $this->inputEncoding);
        }

        return sprintf('xml(%s)', implode(', ', $params));
    }
}
