<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Steps;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Contracts\StepResultInterface;
use LaraForge\Workflows\Step;

class BranchStep extends Step
{
    public function identifier(): string
    {
        return 'branch';
    }

    public function name(): string
    {
        return 'Create Branch';
    }

    public function description(): string
    {
        return 'Create a git branch for the feature implementation';
    }

    public function agentRole(): string
    {
        return 'developer';
    }

    public function skills(): array
    {
        return ['create-branch'];
    }

    public function expectedOutputs(): array
    {
        return [
            'branch_name' => [
                'type' => 'string',
                'description' => 'Name of the created branch',
            ],
        ];
    }

    protected function perform(ProjectContext $context): StepResultInterface
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return $this->failure('No active feature set in context');
        }

        // Check if branch already exists
        $branchName = $feature->branch();

        if ($branchName) {
            return $this->success(
                outputs: ['branch_name' => $branchName],
                metadata: ['skipped' => true, 'reason' => 'Branch already exists']
            );
        }

        // Generate branch name from feature
        $featureId = $feature->id();
        $suggestedBranch = 'feature/'.$this->slugify($featureId);

        return $this->needsReview(
            "Create git branch for feature. Suggested: {$suggestedBranch}",
            outputs: [
                'suggested_branch' => $suggestedBranch,
                'feature_id' => $featureId,
            ],
            metadata: [
                'suggested_skills' => ['create-branch'],
            ]
        );
    }

    public function canExecute(ProjectContext $context): bool
    {
        return $context->currentFeature() !== null;
    }

    public function isComplete(ProjectContext $context): bool
    {
        $feature = $context->currentFeature();

        return $feature?->branch() !== null;
    }

    public function allowsParallel(): bool
    {
        return false; // Branch creation must be sequential
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text) ?? '';
        $text = preg_replace('/\s+/', '-', trim($text)) ?? '';

        return strtolower($text);
    }
}
