<?php

declare(strict_types=1);

namespace LaraForge\Agents\Contracts;

interface TaskInterface
{
    /**
     * Get the unique identifier for this task.
     */
    public function id(): string;

    /**
     * Get the task type (feature, bugfix, refactor, test, review, etc.).
     */
    public function type(): string;

    /**
     * Get the task title/summary.
     */
    public function title(): string;

    /**
     * Get the detailed task description.
     */
    public function description(): string;

    /**
     * Get the current status of the task.
     */
    public function status(): string;

    /**
     * Get the task priority (1-5, where 1 is highest).
     */
    public function priority(): int;

    /**
     * Get task parameters/options.
     *
     * @return array<string, mixed>
     */
    public function params(): array;

    /**
     * Get the parent task ID if this is a subtask.
     */
    public function parentId(): ?string;

    /**
     * Get subtask IDs.
     *
     * @return array<string>
     */
    public function subtaskIds(): array;

    /**
     * Get task dependencies (task IDs that must complete first).
     *
     * @return array<string>
     */
    public function dependencies(): array;

    /**
     * Get assigned agent identifier.
     */
    public function assignee(): ?string;

    /**
     * Get associated feature identifier.
     */
    public function featureId(): ?string;

    /**
     * Get task metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
