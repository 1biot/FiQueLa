<?php

namespace UQL\Stream;

use UQL\Exceptions\InvalidFormat;

/**
 * @implements Stream<\Generator>
 */
abstract class XmlProvider extends StreamProvider implements Stream
{
    private ?\Generator $stream = null;

    protected function __construct(private \XMLReader $xmlReader)
    {
    }

    /**
     * @param \Generator $stream
     */
    public function setStream($stream): void
    {
        if (!$stream instanceof \Generator) {
            throw new \InvalidArgumentException('Expected Generator');
        }

        $this->stream = $stream;
    }

    /**
     * @throws InvalidFormat
     */
    public function getStream(?string $query): ?\Generator
    {
        if ($query === null) {
            return null;
        } elseif ($this->stream !== null) {
            return $this->stream;
        }

        $depth = substr_count($query, '.');
        while ($this->xmlReader->read()) {
            if (
                $this->xmlReader->nodeType == \XMLReader::ELEMENT
                && in_array($this->xmlReader->localName, explode('.', $query))
                && $this->xmlReader->depth === $depth
            ) {
                try {
                    $item = new \SimpleXMLElement($this->xmlReader->readOuterXml(), LIBXML_NOCDATA);
                    yield $this->itemToArray($item);
                } catch (\Exception $e) {
                    throw new InvalidFormat($e->getMessage());
                }
            }
        }
    }

    /**
     * @param \SimpleXMLElement $element
     * @return string|array<mixed>
     */
    private function itemToArray(\SimpleXMLElement $element): string|array
    {
        $result = [];

        // Convert attributes to an array under the key '@attributes'
        foreach ($element->attributes() as $attributeName => $attributeValue) {
            $result['@attributes'][$attributeName] = (string) $attributeValue;
        }

        // Conversion of attributes with namespaces
        foreach ($element->getNamespaces(true) as $prefix => $namespace) {
            foreach ($element->attributes($namespace) as $attributeName => $attributeValue) {
                $key = $prefix ? "{$prefix}:{$attributeName}" : $attributeName;
                $result['@attributes'][$key] = (string) $attributeValue;
            }
        }

        // Conversion of child elements to an array
        foreach ($element->children() as $childName => $childElement) {
            $childArray = $this->itemToArray($childElement);
            if (isset($result[$childName])) {
                // If multiple elements with the same name exist, create an array
                if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
                    $result[$childName] = [$result[$childName]];
                }
                $result[$childName][] = $childArray;
            } else {
                $result[$childName] = $childArray;
            }
        }

        // Conversion of child elements with namespaces
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

        // If the element has no children and attributes, return a simple value
        $value = trim((string) $element);
        if ($value !== '' && empty($result)) {
            return $value;
        }

        // If the element has children or attributes but also a text value, add it as 'value'
        if ($value !== '') {
            $result['value'] = $value;
        }

        return $result;
    }
}
