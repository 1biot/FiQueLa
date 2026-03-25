<?php

namespace FQL\Stream\Writers;

use FQL\Interface\Writer;
use FQL\Query\FileQuery;

class XmlWriter implements Writer
{
    private \XMLWriter $writer;
    private string $rootElement;
    private string $rowElement;

    public function __construct(private readonly FileQuery $fileQuery)
    {
        $this->writer = new \XMLWriter();
        $this->writer->openUri($this->fileQuery->file ?? 'php://memory');
        $this->writer->setIndent(true);

        $encoding = (string) $this->fileQuery->getParam('encoding', 'utf-8');
        $this->writer->startDocument('1.0', $encoding);

        [$this->rootElement, $this->rowElement] = $this->resolveElements($this->fileQuery->query);
        $this->writer->startElement($this->rootElement);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function write(array $row): void
    {
        $this->writer->startElement($this->rowElement);
        foreach ($row as $key => $value) {
            $elementName = $this->sanitizeElementName((string) $key);
            $this->writer->startElement($elementName);
            if (is_scalar($value) || $value === null) {
                $this->writer->text((string) ($value ?? ''));
            } else {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $this->writer->text($encoded === false ? '' : $encoded);
            }
            $this->writer->endElement();
        }
        $this->writer->endElement();
    }

    public function close(): void
    {
        $this->writer->endElement();
        $this->writer->endDocument();
        $this->writer->flush();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveElements(?string $query): array
    {
        if ($query === null || $query === '') {
            return ['rows', 'row'];
        }

        $parts = array_values(array_filter(explode('.', $query), static fn (string $part) => $part !== ''));
        $root = $parts[0] ?? 'rows';
        $row = $parts[1] ?? 'row';

        return [$this->sanitizeElementName($root), $this->sanitizeElementName($row)];
    }

    private function sanitizeElementName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_.:-]/', '_', $name) ?? 'field';
        if ($name === '' || preg_match('/^[^A-Za-z_]/', $name) === 1) {
            $name = 'field_' . $name;
        }

        return $name;
    }
}
