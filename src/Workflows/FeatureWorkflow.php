<?php

declare(strict_types=1);

namespace LaraForge\Workflows;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Steps\BranchStep;
use LaraForge\Workflows\Steps\DesignStep;
use LaraForge\Workflows\Steps\ImplementStep;
use LaraForge\Workflows\Steps\MergeStep;
use LaraForge\Workflows\Steps\RequirementsStep;
use LaraForge\Workflows\Steps\ReviewStep;
use LaraForge\Workflows\Steps\TestContractStep;
use LaraForge\Workflows\Steps\VerifyStep;

class FeatureWorkflow extends Workflow
{
    public function identifier(): string
    {
        return 'feature';
    }

    public function name(): string
    {
        return 'Feature Workflow';
    }

    public function description(): string
    {
        return 'Complete feature development lifecycle: PRD → FRD → Design → Branch → Tests → Implement → Verify → Review → Merge';
    }

    protected function createSteps(): array
    {
        return [
            new RequirementsStep,
            new DesignStep,
            new BranchStep,
            new TestContractStep,
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
            $feature->setPhase('requirements');
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
            'type' => 'feature',
            'estimated_steps' => 8,
            'supports_parallel' => false,
        ];
    }
}
