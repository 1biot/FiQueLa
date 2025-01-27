<?php

namespace FQL\Stream;

use FQL\Enum;
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
        if ($depth === 0) {
            $depth = 1;
        }

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
     * @param \SimpleXMLElement $element
     * @return string|StreamProviderArrayIteratorValue
     */
    private function itemToArray(\SimpleXMLElement $element): string|array
    {
        $result = [];

        // Convert attributes to an array under the key '@attributes'
        foreach ($element->attributes() as $attributeName => $attributeValue) {
            $result['@attributes'][$attributeName] = Enum\Type::matchByString($attributeValue);
        }

        // Conversion of attributes with namespaces
        foreach ($element->getNamespaces(true) as $prefix => $namespace) {
            foreach ($element->attributes($namespace) as $attributeName => $attributeValue) {
                $key = $prefix ? "{$prefix}:{$attributeName}" : $attributeName;
                $result['@attributes'][$key] = Enum\Type::matchByString($attributeValue);
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
                $result[$childName] = is_string($childArray) ? Enum\Type::matchByString($childArray) : (
                    empty($childArray) ? '' : $childArray
                );
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
            return Enum\Type::matchByString($value);
        }

        // If the element has children or attributes but also a text value, add it as 'value'
        if ($value !== '') {
            $result['value'] = Enum\Type::matchByString($value);
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
        $source = '';
        if ($this->xmlFilePath !== '') {
            $source = sprintf('[xml](%s)', basename($this->xmlFilePath));
        }
        return $source;
    }
}
