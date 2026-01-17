<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Contracts;

interface StepResultInterface
{
    /**
     * Check if the step execution was successful.
     */
    public function isSuccess(): bool;

    /**
     * Get the step that was executed.
     */
    public function step(): StepInterface;

    /**
     * Get the outputs produced by this step.
     *
     * @return array<string, mixed>
     */
    public function outputs(): array;

    /**
     * Get any artifacts generated (files, documents, etc.).
     *
     * @return array<string, mixed>
     */
    public function artifacts(): array;

    /**
     * Get the error message if execution failed.
     */
    public function error(): ?string;

    /**
     * Check if the step needs human review before proceeding.
     */
    public function needsReview(): bool;

    /**
     * Get review notes if review is needed.
     */
    public function reviewNotes(): ?string;

    /**
     * Get execution metadata (duration, agent used, etc.).
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
