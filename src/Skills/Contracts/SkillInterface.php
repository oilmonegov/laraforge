<?php

declare(strict_types=1);

namespace LaraForge\Skills\Contracts;

use LaraForge\Project\ProjectContext;

interface SkillInterface
{
    /**
     * Get the unique identifier for this skill.
     */
    public function identifier(): string;

    /**
     * Get the human-readable name of this skill.
     */
    public function name(): string;

    /**
     * Get a description of what this skill does.
     */
    public function description(): string;

    /**
     * Get the parameter definitions for this skill.
     *
     * @return array<string, array{type: string, description: string, required?: bool, default?: mixed}>
     */
    public function parameters(): array;

    /**
     * Execute the skill with the given parameters.
     */
    public function execute(array $params): SkillResultInterface;

    /**
     * Validate the given parameters before execution.
     */
    public function validate(array $params): ValidationResultInterface;

    /**
     * Check if the skill can be executed in the current project context.
     */
    public function canExecute(ProjectContext $context): bool;

    /**
     * Get the category/group this skill belongs to.
     */
    public function category(): string;

    /**
     * Get tags for skill discovery and filtering.
     *
     * @return array<string>
     */
    public function tags(): array;
}
