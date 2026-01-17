<?php

declare(strict_types=1);

namespace LaraForge\Workflows;

use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\SkillRegistry;
use LaraForge\Workflows\Contracts\StepInterface;
use LaraForge\Workflows\Contracts\StepResultInterface;

abstract class Step implements StepInterface
{
    protected ?LaraForgeInterface $laraforge = null;

    protected ?SkillRegistry $skills = null;

    public function setLaraForge(LaraForgeInterface $laraforge): void
    {
        $this->laraforge = $laraforge;
    }

    public function setSkillRegistry(SkillRegistry $skills): void
    {
        $this->skills = $skills;
    }

    abstract public function identifier(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    abstract public function agentRole(): string;

    abstract public function skills(): array;

    abstract protected function perform(ProjectContext $context): StepResultInterface;

    public function execute(ProjectContext $context): StepResultInterface
    {
        if (! $this->canExecute($context)) {
            return StepResult::failure(
                $this,
                'Step cannot be executed: prerequisites not met'
            );
        }

        return $this->perform($context);
    }

    public function canExecute(ProjectContext $context): bool
    {
        // Check dependencies are complete
        foreach ($this->dependencies() as $depId) {
            if (! $this->isDependencyComplete($depId, $context)) {
                return false;
            }
        }

        // Check required inputs are available
        foreach ($this->requiredInputs() as $name => $spec) {
            if (($spec['required'] ?? true) && ! $context->has($name)) {
                return false;
            }
        }

        return true;
    }

    public function isComplete(ProjectContext $context): bool
    {
        // Check all expected outputs are present
        foreach ($this->expectedOutputs() as $name => $spec) {
            if (! $context->has($name)) {
                return false;
            }
        }

        // Run validation criteria
        foreach ($this->validationCriteria() as $name => $validator) {
            if (! $validator($context)) {
                return false;
            }
        }

        return true;
    }

    public function requiredInputs(): array
    {
        return [];
    }

    public function expectedOutputs(): array
    {
        return [];
    }

    public function validationCriteria(): array
    {
        return [];
    }

    public function dependencies(): array
    {
        return [];
    }

    public function allowsParallel(): bool
    {
        return false;
    }

    protected function isDependencyComplete(string $stepId, ProjectContext $context): bool
    {
        $completedSteps = $context->get('completed_steps', []);

        return in_array($stepId, $completedSteps, true);
    }

    protected function success(
        array $outputs = [],
        array $artifacts = [],
        bool $needsReview = false,
        ?string $reviewNotes = null,
        array $metadata = [],
    ): StepResult {
        return StepResult::success(
            step: $this,
            outputs: $outputs,
            artifacts: $artifacts,
            needsReview: $needsReview,
            reviewNotes: $reviewNotes,
            metadata: $metadata,
        );
    }

    protected function failure(
        string $error,
        array $outputs = [],
        array $artifacts = [],
        array $metadata = [],
    ): StepResult {
        return StepResult::failure(
            step: $this,
            error: $error,
            outputs: $outputs,
            artifacts: $artifacts,
            metadata: $metadata,
        );
    }

    protected function needsReview(
        string $reviewNotes,
        array $outputs = [],
        array $artifacts = [],
        array $metadata = [],
    ): StepResult {
        return StepResult::forReview(
            step: $this,
            reviewNotes: $reviewNotes,
            outputs: $outputs,
            artifacts: $artifacts,
            metadata: $metadata,
        );
    }
}
