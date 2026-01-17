<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Steps;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Contracts\StepResultInterface;
use LaraForge\Workflows\Step;

class ImplementStep extends Step
{
    public function identifier(): string
    {
        return 'implement';
    }

    public function name(): string
    {
        return 'Implementation';
    }

    public function description(): string
    {
        return 'Implement the feature based on design and test contracts';
    }

    public function agentRole(): string
    {
        return 'developer';
    }

    public function skills(): array
    {
        return ['implement', 'api-resource', 'policy', 'manager'];
    }

    public function dependencies(): array
    {
        return ['branch'];
    }

    public function requiredInputs(): array
    {
        return [
            'design_path' => [
                'type' => 'string',
                'description' => 'Path to the design document',
                'required' => false,
            ],
            'test_contract_path' => [
                'type' => 'string',
                'description' => 'Path to the test contract document',
                'required' => false,
            ],
        ];
    }

    public function expectedOutputs(): array
    {
        return [
            'implemented_files' => [
                'type' => 'array',
                'description' => 'List of files that were created/modified',
            ],
        ];
    }

    protected function perform(ProjectContext $context): StepResultInterface
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return $this->failure('No active feature set in context');
        }

        // Gather available documents
        $documents = [
            'frd' => $feature->document('frd'),
            'design' => $feature->document('design'),
            'test_contract' => $feature->document('test-contract'),
        ];

        return $this->needsReview(
            'Implementation ready to begin. Follow pseudocode from design document.',
            outputs: $documents,
            metadata: [
                'suggested_skills' => $this->skills(),
                'implementation_guidelines' => [
                    'Follow pseudocode from design document',
                    'Implement test contracts first (TDD)',
                    'Use appropriate generator skills for boilerplate',
                    'Commit changes incrementally',
                ],
            ]
        );
    }

    public function canExecute(ProjectContext $context): bool
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return false;
        }

        // Need at least a branch
        return $feature->branch() !== null;
    }

    public function isComplete(ProjectContext $context): bool
    {
        // Implementation is complete when marked by context
        return $context->get('implementation_complete', false) === true;
    }

    public function allowsParallel(): bool
    {
        return true; // Multiple developers can work in parallel via worktrees
    }
}
