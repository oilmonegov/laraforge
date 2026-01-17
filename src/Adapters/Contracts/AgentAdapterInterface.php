<?php

declare(strict_types=1);

namespace LaraForge\Adapters\Contracts;

use LaraForge\Agents\Contracts\TaskInterface;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillInterface;

interface AgentAdapterInterface
{
    /**
     * Get the adapter identifier.
     */
    public function identifier(): string;

    /**
     * Get the adapter name.
     */
    public function name(): string;

    /**
     * Check if this adapter is available/applicable.
     */
    public function isAvailable(): bool;

    /**
     * Execute a skill through this adapter.
     *
     * @return array{success: bool, output: mixed, error?: string}
     */
    public function executeSkill(SkillInterface $skill, array $params, ProjectContext $context): array;

    /**
     * Execute a task through this adapter.
     *
     * @return array{success: bool, output: mixed, error?: string}
     */
    public function executeTask(TaskInterface $task, ProjectContext $context): array;

    /**
     * Get adapter-specific metadata for AI context.
     *
     * @return array<string, mixed>
     */
    public function getContextMetadata(): array;

    /**
     * Format output for the specific agent type.
     */
    public function formatOutput(mixed $output): string;
}
