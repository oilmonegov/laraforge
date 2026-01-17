<?php

declare(strict_types=1);

namespace LaraForge\Core\Contracts;

/**
 * Framework Adapter Interface
 *
 * Defines the contract for framework-specific adapters.
 * Each framework (Laravel, Symfony, etc.) implements this to provide
 * framework-specific code generation, patterns, and best practices.
 */
interface FrameworkAdapterInterface
{
    /**
     * Get the framework identifier.
     */
    public function identifier(): string;

    /**
     * Get the framework display name.
     */
    public function name(): string;

    /**
     * Get the framework version.
     */
    public function version(): ?string;

    /**
     * Check if this adapter applies to the current project.
     */
    public function isApplicable(): bool;

    /**
     * Get the directory structure conventions for this framework.
     *
     * @return array<string, mixed>
     */
    public function getDirectoryStructure(): array;

    /**
     * Get stub templates specific to this framework.
     *
     * @return array<string, string> Map of stub name to template path
     */
    public function getStubTemplates(): array;

    /**
     * Get security rules specific to this framework.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSecurityRules(): array;

    /**
     * Get best practices documentation URLs.
     *
     * @return array<string, string>
     */
    public function getDocumentationUrls(): array;

    /**
     * Get coding standards for this framework.
     *
     * @return array<string, mixed>
     */
    public function getCodingStandards(): array;

    /**
     * Get testing conventions for this framework.
     *
     * @return array<string, mixed>
     */
    public function getTestingConventions(): array;

    /**
     * Transform a generic component name to framework-specific path.
     */
    public function resolvePath(string $component, string $name): string;

    /**
     * Get framework-specific context for AI prompts.
     *
     * @return array<string, mixed>
     */
    public function getAiContext(): array;
}
