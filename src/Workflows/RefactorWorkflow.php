<?php

declare(strict_types=1);

namespace LaraForge\Workflows;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Steps\BranchStep;
use LaraForge\Workflows\Steps\DesignStep;
use LaraForge\Workflows\Steps\ImplementStep;
use LaraForge\Workflows\Steps\MergeStep;
use LaraForge\Workflows\Steps\ReviewStep;
use LaraForge\Workflows\Steps\VerifyStep;

class RefactorWorkflow extends Workflow
{
    public function identifier(): string
    {
        return 'refactor';
    }

    public function name(): string
    {
        return 'Refactor Workflow';
    }

    public function description(): string
    {
        return 'Refactoring process: Design → Branch → Implement → Verify → Review → Merge';
    }

    protected function createSteps(): array
    {
        return [
            new DesignStep,
            new BranchStep,
            new ImplementStep,
            new VerifyStep,
            new ReviewStep,
            new MergeStep,
        ];
    }

    public function onStart(ProjectContext $context): void
    {
        $feature = $context->currentFeature();
        if ($feature) {
            $feature->setStatus('in_progress');
            $feature->setPhase('design');
        }
    }

    public function onComplete(ProjectContext $context): void
    {
        $feature = $context->currentFeature();
        if ($feature) {
            $feature->setStatus('completed');
            $feature->setPhase('completed');
            $feature->setProgress(100);
        }
    }

    public function metadata(): array
    {
        return [
            'type' => 'refactor',
            'estimated_steps' => 6,
            'supports_parallel' => false,
        ];
    }
}
