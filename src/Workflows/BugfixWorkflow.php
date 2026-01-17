<?php

declare(strict_types=1);

namespace LaraForge\Workflows;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Steps\BranchStep;
use LaraForge\Workflows\Steps\ImplementStep;
use LaraForge\Workflows\Steps\MergeStep;
use LaraForge\Workflows\Steps\ReviewStep;
use LaraForge\Workflows\Steps\VerifyStep;

class BugfixWorkflow extends Workflow
{
    public function identifier(): string
    {
        return 'bugfix';
    }

    public function name(): string
    {
        return 'Bugfix Workflow';
    }

    public function description(): string
    {
        return 'Bug fix process: Branch → Fix → Verify → Review → Merge';
    }

    protected function createSteps(): array
    {
        return [
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
            $feature->setPhase('implementation');
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
            'type' => 'bugfix',
            'estimated_steps' => 5,
            'supports_parallel' => false,
        ];
    }
}
