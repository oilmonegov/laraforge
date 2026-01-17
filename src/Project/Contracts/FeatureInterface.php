<?php

declare(strict_types=1);

namespace LaraForge\Project\Contracts;

interface FeatureInterface
{
    /**
     * Get the feature identifier.
     */
    public function id(): string;

    /**
     * Get the feature title.
     */
    public function title(): string;

    /**
     * Get the feature description.
     */
    public function description(): string;

    /**
     * Get the feature status (planning, requirements, design, implementation, testing, review, completed).
     */
    public function status(): string;

    /**
     * Set the feature status.
     */
    public function setStatus(string $status): void;

    /**
     * Get the current phase (more granular than status).
     */
    public function phase(): string;

    /**
     * Set the current phase.
     */
    public function setPhase(string $phase): void;

    /**
     * Get the associated branch name.
     */
    public function branch(): ?string;

    /**
     * Set the branch name.
     */
    public function setBranch(string $branch): void;

    /**
     * Get the assigned agent identifier.
     */
    public function assignee(): ?string;

    /**
     * Set the assignee.
     */
    public function setAssignee(string $agentId): void;

    /**
     * Get the progress percentage (0-100).
     */
    public function progress(): int;

    /**
     * Set the progress percentage.
     */
    public function setProgress(int $progress): void;

    /**
     * Get associated document paths.
     *
     * @return array<string, string>
     */
    public function documents(): array;

    /**
     * Add a document reference.
     */
    public function addDocument(string $type, string $path): void;

    /**
     * Get a document path by type.
     */
    public function document(string $type): ?string;

    /**
     * Get feature priority (1-5, where 1 is highest).
     */
    public function priority(): int;

    /**
     * Set the priority.
     */
    public function setPriority(int $priority): void;

    /**
     * Get feature tags.
     *
     * @return array<string>
     */
    public function tags(): array;

    /**
     * Add a tag.
     */
    public function addTag(string $tag): void;

    /**
     * Get feature metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;

    /**
     * Set a metadata value.
     */
    public function setMetadata(string $key, mixed $value): void;

    /**
     * Get creation timestamp.
     */
    public function createdAt(): \DateTimeInterface;

    /**
     * Get last updated timestamp.
     */
    public function updatedAt(): \DateTimeInterface;

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
