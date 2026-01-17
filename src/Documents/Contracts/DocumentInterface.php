<?php

declare(strict_types=1);

namespace LaraForge\Documents\Contracts;

interface DocumentInterface
{
    /**
     * Get the document type (prd, frd, design, test-contract, etc.).
     */
    public function type(): string;

    /**
     * Get the document title.
     */
    public function title(): string;

    /**
     * Get the document version.
     */
    public function version(): string;

    /**
     * Get the document status (draft, review, approved, in_progress, completed).
     */
    public function status(): string;

    /**
     * Get the associated feature identifier.
     */
    public function featureId(): ?string;

    /**
     * Get the document content as structured data.
     *
     * @return array<string, mixed>
     */
    public function content(): array;

    /**
     * Get the raw content (markdown, yaml, etc.).
     */
    public function rawContent(): string;

    /**
     * Get the file path where this document is stored.
     */
    public function path(): ?string;

    /**
     * Check if the document is valid according to its schema.
     */
    public function isValid(): bool;

    /**
     * Get validation errors.
     *
     * @return array<string>
     */
    public function validationErrors(): array;

    /**
     * Get document metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;

    /**
     * Convert the document to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Serialize the document to its storage format.
     */
    public function serialize(): string;
}
