<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Contracts;

use LaraForge\Project\ProjectContext;

interface StepInterface
{
    /**
     * Get the unique identifier for this step.
     */
    public function identifier(): string;

    /**
     * Get the human-readable name of this step.
     */
    public function name(): string;

    /**
     * Get a description of what this step does.
     */
    public function description(): string;

    /**
     * Get the agent role best suited for this step.
     */
    public function agentRole(): string;

    /**
     * Get the skills typically used in this step.
     *
     * @return array<string>
     */
    public function skills(): array;

    /**
     * Check if this step can be executed in the current context.
     */
    public function canExecute(ProjectContext $context): bool;

    /**
     * Check if this step is complete.
     */
    public function isComplete(ProjectContext $context): bool;

    /**
     * Get the required inputs for this step.
     *
     * @return array<string, array{type: string, description: string, required?: bool}>
     */
    public function requiredInputs(): array;

    /**
     * Get the expected outputs from this step.
     *
     * @return array<string, array{type: string, description: string}>
     */
    public function expectedOutputs(): array;

    /**
     * Get validation criteria for step completion.
     *
     * @return array<string, callable>
     */
    public function validationCriteria(): array;

    /**
     * Execute this step.
     */
    public function execute(ProjectContext $context): StepResultInterface;

    /**
     * Get step dependencies (step identifiers that must complete first).
     *
     * @return array<string>
     */
    public function dependencies(): array;

    /**
     * Check if this step can run in parallel with others.
     */
    public function allowsParallel(): bool;
}
