<?php

declare(strict_types=1);

namespace LaraForge\Agents\Contracts;

interface AgentResultInterface
{
    /**
     * Check if the agent execution was successful.
     */
    public function isSuccess(): bool;

    /**
     * Get the task that was executed.
     */
    public function task(): TaskInterface;

    /**
     * Get the primary output/result of the execution.
     */
    public function output(): mixed;

    /**
     * Get any artifacts generated (files, documents, etc.).
     *
     * @return array<string, mixed>
     */
    public function artifacts(): array;

    /**
     * Get skill results from skills executed during the task.
     *
     * @return array<\LaraForge\Skills\Contracts\SkillResultInterface>
     */
    public function skillResults(): array;

    /**
     * Get recommended next tasks.
     *
     * @return array<array{type: string, title: string, description?: string, params?: array}>
     */
    public function nextTasks(): array;

    /**
     * Get the error message if execution failed.
     */
    public function error(): ?string;

    /**
     * Get handoff information if work was handed to another agent.
     *
     * @return array{agent: string, reason: string, data: array}|null
     */
    public function handoff(): ?array;

    /**
     * Get execution metadata (duration, resources used, etc.).
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
