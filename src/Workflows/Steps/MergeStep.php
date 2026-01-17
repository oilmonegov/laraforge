<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Steps;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Contracts\StepResultInterface;
use LaraForge\Workflows\Step;

class MergeStep extends Step
{
    public function identifier(): string
    {
        return 'merge';
    }

    public function name(): string
    {
        return 'Merge';
    }

    public function description(): string
    {
        return 'Merge the feature branch into the target branch';
    }

    public function agentRole(): string
    {
        return 'developer';
    }

    public function skills(): array
    {
        return ['merge-branch', 'create-pr'];
    }

    public function dependencies(): array
    {
        return ['review'];
    }

    public function requiredInputs(): array
    {
        return [
            'branch_name' => [
                'type' => 'string',
                'description' => 'Branch to merge',
                'required' => true,
            ],
            'target_branch' => [
                'type' => 'string',
                'description' => 'Target branch to merge into',
                'required' => false,
            ],
        ];
    }

    public function expectedOutputs(): array
    {
        return [
            'merged' => [
                'type' => 'boolean',
                'description' => 'Whether the merge was successful',
            ],
            'merge_commit' => [
                'type' => 'string',
                'description' => 'Merge commit hash',
            ],
        ];
    }

    protected function perform(ProjectContext $context): StepResultInterface
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return $this->failure('No active feature set in context');
        }

        $branchName = $feature->branch();
        if (! $branchName) {
            return $this->failure('No branch associated with feature');
        }

        $targetBranch = $context->get('target_branch', 'main');

        return $this->needsReview(
            "Ready to merge {$branchName} into {$targetBranch}.",
            outputs: [
                'branch_name' => $branchName,
                'target_branch' => $targetBranch,
            ],
            metadata: [
                'suggested_skills' => ['merge-branch', 'create-pr'],
                'merge_options' => [
                    'squash' => 'Squash all commits into one',
                    'rebase' => 'Rebase onto target branch',
                    'merge' => 'Create merge commit',
                ],
            ]
        );
    }

    public function canExecute(ProjectContext $context): bool
    {
        // Need review approval
        if ($context->get('review_approved', false) !== true) {
            return false;
        }

        $feature = $context->currentFeature();

        return $feature?->branch() !== null;
    }

    public function isComplete(ProjectContext $context): bool
    {
        return $context->get('merged', false) === true;
    }

    public function allowsParallel(): bool
    {
        return false; // Merge must be sequential
    }
}
