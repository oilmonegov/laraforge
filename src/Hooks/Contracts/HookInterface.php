<?php

declare(strict_types=1);

namespace LaraForge\Hooks\Contracts;

use LaraForge\Project\ProjectContext;

interface HookInterface
{
    /**
     * Get the unique identifier for this hook.
     */
    public function identifier(): string;

    /**
     * Get the hook name.
     */
    public function name(): string;

    /**
     * Get the hook type (pre-workflow, post-workflow, pre-step, post-step, validation).
     */
    public function type(): string;

    /**
     * Get the priority (lower runs first).
     */
    public function priority(): int;

    /**
     * Check if this hook should run for the given context.
     */
    public function shouldRun(ProjectContext $context, array $eventData = []): bool;

    /**
     * Execute the hook.
     *
     * @return array{continue: bool, data?: array, error?: string}
     */
    public function execute(ProjectContext $context, array $eventData = []): array;

    /**
     * Check if the hook execution can be skipped.
     */
    public function isSkippable(): bool;

    /**
     * Get hook metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
