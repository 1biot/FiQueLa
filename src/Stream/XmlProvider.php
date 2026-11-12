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
    private const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';

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
     * Streams matching records one by one, built directly from `XMLReader`
     * nodes (see `readRecord()`). Only the current record is held in memory.
     *
     * Internal DTD entities are expanded. External entities are never loaded:
     * while the generator runs, a resolver that refuses them is installed and
     * the previous resolver is restored before every `yield`, so code running
     * between records is not affected.
     *
     * @throws Exception\UnexpectedValueException
     * @throws Exception\UnableOpenFileException
     */
    public function getStreamGenerator(?string $query): \Generator
    {
        $query = $query ?? Interface\Query::FROM_ALL;
        $depth = substr_count($query, '.');
        $matchAll = $query === Interface\Query::FROM_ALL;
        $names = $query === '' ? [] : array_fill_keys(explode('.', $query), true);

        $blockedEntity = null;
        $entityGuard = static function (?string $publicId, string $systemId) use (&$blockedEntity) {
            $blockedEntity ??= $systemId;
            return null;
        };

        $xmlReader = $this->openReader();
        $previousLoader = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader($entityGuard);
        $guarded = true;
        try {
            while ($this->moveToNextRecord($xmlReader, $depth, $matchAll, $names)) {
                $record = $this->readRecord($xmlReader);
                if ($blockedEntity !== null) {
                    throw new Exception\UnexpectedValueException(
                        sprintf('External XML entities are not supported: %s', $blockedEntity)
                    );
                }

                libxml_set_external_entity_loader($previousLoader);
                $guarded = false;
                yield $record;
                $previousLoader = libxml_get_external_entity_loader();
                libxml_set_external_entity_loader($entityGuard);
                $guarded = true;
            }
            if ($blockedEntity !== null) {
                throw new Exception\UnexpectedValueException(
                    sprintf('External XML entities are not supported: %s', $blockedEntity)
                );
            }
        } finally {
            if ($guarded) {
                libxml_set_external_entity_loader($previousLoader);
            }
            $xmlReader->close();
        }
    }

    /**
     * @throws Exception\UnableOpenFileException
     */
    private function openReader(): \XMLReader
    {
        $xmlReader = \XMLReader::open($this->xmlFilePath, $this->inputEncoding);
        if (!$xmlReader) {
            throw new Exception\UnableOpenFileException('Unable to open XML file.');
        }
        $xmlReader->setParserProperty(\XMLReader::SUBST_ENTITIES, true);
        return $xmlReader;
    }

    /**
     * Advances the reader to the next element selected by the query: an
     * element at the query depth whose local name is one of the query path
     * segments (or any element at depth 0 for `*`).
     *
     * @param array<string, true> $names
     */
    private function moveToNextRecord(\XMLReader $xmlReader, int $depth, bool $matchAll, array $names): bool
    {
        while ($xmlReader->read()) {
            if (
                $xmlReader->nodeType === \XMLReader::ELEMENT
                && $xmlReader->depth === $depth
                && ($matchAll || isset($names[$xmlReader->localName]))
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Builds the PHP value of the record the reader is positioned on, reading
     * its nodes directly, and leaves the reader on the record's end tag (or on
     * the record itself when it is self-closing), so the next `read()`
     * continues right after the record.
     *
     * Shape:
     * - element and attribute keys are the qualified names as written in the
     *   document (`title`, `g:price`, `xml:lang`), in document order;
     *   namespace declarations (`xmlns`, `xmlns:*`) are not data and are skipped;
     * - attributes go under `@attributes`, all values stay raw strings —
     *   downstream coercion happens lazily in `Enum\Operator::evaluate()` /
     *   `Sql\Runtime\ExpressionEvaluator`;
     * - repeated siblings collapse into a list;
     * - a leaf element (no attributes, no children) is its trimmed text, the
     *   empty string for `<foo/>` / `<foo></foo>` (so `IS NULL` / `= ''` match);
     * - text next to children or attributes is stored trimmed under `value`;
     * - CDATA is treated as text, comments and processing instructions are ignored.
     *
     * @return string|StreamProviderArrayIteratorValue
     * @throws Exception\UnexpectedValueException
     */
    private function readRecord(\XMLReader $xmlReader): string|array
    {
        /** @var array<int, array<string, mixed>> $results */
        $results = [];
        /** @var string[] $texts */
        $texts = [];
        /** @var string[] $names */
        $names = [];
        $level = -1;

        do {
            switch ($xmlReader->nodeType) {
                case \XMLReader::ELEMENT:
                    $result = [];
                    if ($xmlReader->hasAttributes) {
                        while ($xmlReader->moveToNextAttribute()) {
                            if ($xmlReader->namespaceURI !== self::XMLNS_NAMESPACE) {
                                $result['@attributes'][$xmlReader->name] = $xmlReader->value;
                            }
                        }
                        $xmlReader->moveToElement();
                    }

                    if (!$xmlReader->isEmptyElement) {
                        $level++;
                        $results[$level] = $result;
                        $texts[$level] = '';
                        $names[$level] = $xmlReader->name;
                        break;
                    }

                    // Self-closing element: no text, finalize right away.
                    $value = $result === [] ? '' : $result;
                    if ($level < 0) {
                        return $value;
                    }
                    $this->appendChild($results[$level], $xmlReader->name, $value);
                    break;

                case \XMLReader::TEXT:
                case \XMLReader::CDATA:
                case \XMLReader::WHITESPACE:
                case \XMLReader::SIGNIFICANT_WHITESPACE:
                    $texts[$level] .= $xmlReader->value;
                    break;

                case \XMLReader::END_ELEMENT:
                    $result = $results[$level];
                    $text = trim($texts[$level]);
                    if ($result === []) {
                        $value = $text;
                    } else {
                        if ($text !== '') {
                            $result['value'] = $text;
                        }
                        $value = $result;
                    }

                    if ($level === 0) {
                        return $value;
                    }

                    $name = $names[$level];
                    unset($results[$level], $texts[$level], $names[$level]);
                    $level--;
                    $this->appendChild($results[$level], $name, $value);
                    break;

                default:
                    // Comments, processing instructions, entity boundaries — not data.
                    break;
            }
        } while ($xmlReader->read());

        throw new Exception\UnexpectedValueException('Unable to parse XML record: unexpected end of document.');
    }

    /**
     * Adds a child value under `$name`; a repeated sibling turns the entry
     * into a list.
     *
     * @param array<string, mixed> $result
     * @param string|array<string, mixed> $child
     */
    private function appendChild(array &$result, string $name, string|array $child): void
    {
        if (isset($result[$name])) {
            if (!is_array($result[$name]) || !isset($result[$name][0])) {
                $result[$name] = [$result[$name]];
            }
            $result[$name][] = $child;
        } else {
            $result[$name] = $child;
        }
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
