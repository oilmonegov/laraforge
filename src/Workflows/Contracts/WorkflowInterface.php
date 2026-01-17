<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Contracts;

use LaraForge\Project\ProjectContext;

interface WorkflowInterface
{
    /**
     * Get the unique identifier for this workflow.
     */
    public function identifier(): string;

    /**
     * Get the human-readable name of this workflow.
     */
    public function name(): string;

    /**
     * Get a description of this workflow.
     */
    public function description(): string;

    /**
     * Get the steps in this workflow in order.
     *
     * @return array<StepInterface>
     */
    public function steps(): array;

    /**
     * Get the current step based on project context.
     */
    public function currentStep(ProjectContext $context): ?StepInterface;

    /**
     * Get the next step to execute.
     */
    public function nextStep(ProjectContext $context): ?StepInterface;

    /**
     * Check if the workflow can start in the current context.
     */
    public function canStart(ProjectContext $context): bool;

    /**
     * Check if the workflow is complete.
     */
    public function isComplete(ProjectContext $context): bool;

    /**
     * Called when the workflow starts.
     */
    public function onStart(ProjectContext $context): void;

    /**
     * Called when the workflow completes.
     */
    public function onComplete(ProjectContext $context): void;

    /**
     * Called when a step completes.
     */
    public function onStepComplete(StepInterface $step, ProjectContext $context): void;

    /**
     * Get workflow metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
