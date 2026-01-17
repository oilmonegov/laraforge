<?php

declare(strict_types=1);

namespace LaraForge\Worktree\Contracts;

interface ConflictInterface
{
    /**
     * Get the file path with the conflict.
     */
    public function filePath(): string;

    /**
     * Get the conflict type (content, rename, delete, etc.).
     */
    public function type(): string;

    /**
     * Get the sessions involved in the conflict.
     *
     * @return array<string>
     */
    public function sessionIds(): array;

    /**
     * Get the branches involved in the conflict.
     *
     * @return array<string>
     */
    public function branches(): array;

    /**
     * Get the conflicting content sections.
     *
     * @return array<array{branch: string, content: string}>
     */
    public function sections(): array;

    /**
     * Get a description of the conflict.
     */
    public function description(): string;

    /**
     * Get suggested resolution strategies.
     *
     * @return array<array{strategy: string, description: string}>
     */
    public function suggestedResolutions(): array;

    /**
     * Check if the conflict can be auto-resolved.
     */
    public function canAutoResolve(): bool;

    /**
     * Get the auto-resolution result if available.
     */
    public function autoResolution(): ?string;
}
