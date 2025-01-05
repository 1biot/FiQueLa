<?php

namespace UQL\Stream;

use UQL\Enum\Type;

/**
 * @implements Stream<\Generator>
 */
abstract class XmlProvider extends StreamProvider implements Stream
{
    protected function __construct(private readonly string $xmlFilePath, private ?string $encoding = null)
    {
    }

    public function setEncoding(?string $encoding): self
    {
        $this->encoding = $encoding;
        return $this;
    }

    public function getStream(?string $query): ?\ArrayIterator
    {
        $generator = $this->getStreamGenerator($query);
        return $generator ? new \ArrayIterator(iterator_to_array($generator)) : null;
    }

    public function getStreamGenerator(?string $query): ?\Generator
    {
        if ($query === null) {
            return null;
        }

        $xmlReader = \XMLReader::open($this->xmlFilePath, $this->encoding);
        $depth = substr_count($query, '.');
        while ($xmlReader->read()) {
            if (
                $xmlReader->nodeType == \XMLReader::ELEMENT
                && in_array($xmlReader->localName, explode('.', $query))
                && $xmlReader->depth === $depth
            ) {
                try {
                    $item = new \SimpleXMLElement($xmlReader->readOuterXml(), LIBXML_NOCDATA);
                    yield $this->itemToArray($item);
                } catch (\Exception $e) {
                    trigger_error($e->getMessage(), E_USER_WARNING);
                    break;
                }
            }
        }
        $xmlReader->close();
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
            $result['@attributes'][$attributeName] = Type::matchByString($attributeValue);
        }

        // Conversion of attributes with namespaces
        foreach ($element->getNamespaces(true) as $prefix => $namespace) {
            foreach ($element->attributes($namespace) as $attributeName => $attributeValue) {
                $key = $prefix ? "{$prefix}:{$attributeName}" : $attributeName;
                $result['@attributes'][$key] = Type::matchByString($attributeValue);
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
                $result[$childName] = is_string($childArray) ? Type::matchByString($childArray) : $childArray;
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
            return Type::matchByString($value);
        }

        // If the element has children or attributes but also a text value, add it as 'value'
        if ($value !== '') {
            $result['value'] = Type::matchByString($value);
        }

        return $result;
    }
}
