<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Steps;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Contracts\StepResultInterface;
use LaraForge\Workflows\Step;

class TestContractStep extends Step
{
    public function identifier(): string
    {
        return 'test-contract';
    }

    public function name(): string
    {
        return 'Test Contracts';
    }

    public function description(): string
    {
        return 'Create test contracts/specifications before implementation';
    }

    public function agentRole(): string
    {
        return 'architect';
    }

    public function skills(): array
    {
        return ['create-test-contract'];
    }

    public function dependencies(): array
    {
        return ['design'];
    }

    public function requiredInputs(): array
    {
        return [
            'frd_path' => [
                'type' => 'string',
                'description' => 'Path to the FRD document',
                'required' => true,
            ],
        ];
    }

    public function expectedOutputs(): array
    {
        return [
            'test_contract_path' => [
                'type' => 'string',
                'description' => 'Path to the generated test contract document',
            ],
        ];
    }

    protected function perform(ProjectContext $context): StepResultInterface
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return $this->failure('No active feature set in context');
        }

        // Check if test contract already exists
        $contractPath = $feature->document('test-contract');

        if ($contractPath) {
            return $this->success(
                outputs: ['test_contract_path' => $contractPath],
                metadata: ['skipped' => true, 'reason' => 'Test contract already exists']
            );
        }

        // Ensure FRD and design exist
        $frdPath = $feature->document('frd');
        $designPath = $feature->document('design');

        if (! $frdPath) {
            return $this->failure('FRD document is required before creating test contracts');
        }

        return $this->needsReview(
            'Test contracts need to be created. Use create-test-contract skill.',
            outputs: [
                'frd_path' => $frdPath,
                'design_path' => $designPath,
            ],
            metadata: [
                'suggested_skills' => ['create-test-contract'],
            ]
        );
    }

    public function canExecute(ProjectContext $context): bool
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return false;
        }

        return $feature->document('frd') !== null;
    }

    public function isComplete(ProjectContext $context): bool
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return false;
        }

        return $feature->document('test-contract') !== null;
    }
}
