<?php

declare(strict_types=1);

namespace LaraForge\Documents\Contracts;

interface DocumentParserInterface
{
    /**
     * Get the document type this parser handles.
     */
    public function type(): string;

    /**
     * Get supported file extensions.
     *
     * @return array<string>
     */
    public function extensions(): array;

    /**
     * Parse a document from file content.
     */
    public function parse(string $content, ?string $path = null): DocumentInterface;

    /**
     * Parse a document from a file path.
     */
    public function parseFile(string $path): DocumentInterface;

    /**
     * Check if the parser can handle the given content.
     */
    public function canParse(string $content): bool;

    /**
     * Validate document content without fully parsing.
     *
     * @return array<string>
     */
    public function validateContent(string $content): array;
}
