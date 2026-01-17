<?php

declare(strict_types=1);

namespace LaraForge\Workflows;

use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\SkillRegistry;
use LaraForge\Workflows\Contracts\StepInterface;
use LaraForge\Workflows\Contracts\WorkflowInterface;

abstract class Workflow implements WorkflowInterface
{
    protected ?LaraForgeInterface $laraforge = null;

    protected ?SkillRegistry $skills = null;

    /**
     * @var array<StepInterface>
     */
    protected array $steps = [];

    public function setLaraForge(LaraForgeInterface $laraforge): void
    {
        $this->laraforge = $laraforge;

        foreach ($this->steps as $step) {
            if ($step instanceof Step) {
                $step->setLaraForge($laraforge);
            }
        }
    }

    public function setSkillRegistry(SkillRegistry $skills): void
    {
        $this->skills = $skills;

        foreach ($this->steps as $step) {
            if ($step instanceof Step) {
                $step->setSkillRegistry($skills);
            }
        }
    }

    abstract public function identifier(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * Get the step instances for this workflow.
     * Override this in concrete workflows to define steps.
     *
     * @return array<StepInterface>
     */
    abstract protected function createSteps(): array;

    public function steps(): array
    {
        if (empty($this->steps)) {
            $this->steps = $this->createSteps();

            // Inject dependencies into steps
            foreach ($this->steps as $step) {
                if ($step instanceof Step) {
                    if ($this->laraforge) {
                        $step->setLaraForge($this->laraforge);
                    }
                    if ($this->skills) {
                        $step->setSkillRegistry($this->skills);
                    }
                }
            }
        }

        return $this->steps;
    }

    public function currentStep(ProjectContext $context): ?StepInterface
    {
        $completedSteps = $context->get('completed_steps', []);

        foreach ($this->steps() as $step) {
            if (! in_array($step->identifier(), $completedSteps, true)) {
                return $step;
            }
        }

        return null;
    }

    public function nextStep(ProjectContext $context): ?StepInterface
    {
        $current = $this->currentStep($context);

        if (! $current) {
            return null;
        }

        // Check if current step can be executed
        if ($current->canExecute($context)) {
            return $current;
        }

        return null;
    }

    public function canStart(ProjectContext $context): bool
    {
        $steps = $this->steps();

        if (empty($steps)) {
            return false;
        }

        $firstStep = reset($steps);

        return $firstStep->canExecute($context);
    }

    public function isComplete(ProjectContext $context): bool
    {
        $completedSteps = $context->get('completed_steps', []);

        foreach ($this->steps() as $step) {
            if (! in_array($step->identifier(), $completedSteps, true)) {
                return false;
            }
        }

        return true;
    }

    public function onStart(ProjectContext $context): void
    {
        // Can be overridden in concrete workflows
    }

    public function onComplete(ProjectContext $context): void
    {
        // Can be overridden in concrete workflows
    }

    public function onStepComplete(StepInterface $step, ProjectContext $context): void
    {
        $completedSteps = $context->get('completed_steps', []);
        $completedSteps[] = $step->identifier();
        $context->set('completed_steps', $completedSteps);
    }

    public function metadata(): array
    {
        return [];
    }

    public function getStep(string $identifier): ?StepInterface
    {
        foreach ($this->steps() as $step) {
            if ($step->identifier() === $identifier) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Get progress as a percentage.
     */
    public function progress(ProjectContext $context): int
    {
        $steps = $this->steps();
        $total = count($steps);

        if ($total === 0) {
            return 100;
        }

        $completedSteps = $context->get('completed_steps', []);
        $completed = count(array_intersect(
            array_map(fn ($s) => $s->identifier(), $steps),
            $completedSteps
        ));

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Get a summary of workflow status.
     *
     * @return array<string, mixed>
     */
    public function status(ProjectContext $context): array
    {
        $completedSteps = $context->get('completed_steps', []);
        $current = $this->currentStep($context);

        $stepStatuses = [];
        foreach ($this->steps() as $step) {
            $id = $step->identifier();
            $stepStatuses[$id] = [
                'name' => $step->name(),
                'completed' => in_array($id, $completedSteps, true),
                'current' => $current?->identifier() === $id,
                'can_execute' => $step->canExecute($context),
            ];
        }

        return [
            'workflow' => $this->identifier(),
            'progress' => $this->progress($context),
            'is_complete' => $this->isComplete($context),
            'current_step' => $current?->identifier(),
            'steps' => $stepStatuses,
        ];
    }
}
