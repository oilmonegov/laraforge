<?php

declare(strict_types=1);

namespace LaraForge\Agents\Contracts;

use LaraForge\Project\ProjectContext;

interface AgentInterface
{
    /**
     * Get the unique identifier for this agent.
     */
    public function identifier(): string;

    /**
     * Get the human-readable name of this agent.
     */
    public function name(): string;

    /**
     * Get the role of this agent (analyst, architect, developer, tester, reviewer, pm).
     */
    public function role(): string;

    /**
     * Get a description of this agent's responsibilities.
     */
    public function description(): string;

    /**
     * Get the skill identifiers this agent can use.
     *
     * @return array<string>
     */
    public function capabilities(): array;

    /**
     * Execute a task with the given context.
     */
    public function execute(TaskInterface $task, ProjectContext $context): AgentResultInterface;

    /**
     * Hand off work to another agent.
     */
    public function handoff(AgentInterface $to, ProjectContext $context, array $data = []): void;

    /**
     * Check if this agent can handle the given task.
     */
    public function canHandle(TaskInterface $task): bool;

    /**
     * Get the priority for handling a specific task (higher = more suitable).
     */
    public function priority(TaskInterface $task): int;
}
