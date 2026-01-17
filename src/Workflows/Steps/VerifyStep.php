<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Steps;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Contracts\StepResultInterface;
use LaraForge\Workflows\Step;

class VerifyStep extends Step
{
    public function identifier(): string
    {
        return 'verify';
    }

    public function name(): string
    {
        return 'Verification';
    }

    public function description(): string
    {
        return 'Verify implementation against test contracts and run all tests';
    }

    public function agentRole(): string
    {
        return 'tester';
    }

    public function skills(): array
    {
        return ['validate-tests', 'run-tests'];
    }

    public function dependencies(): array
    {
        return ['implement'];
    }

    public function expectedOutputs(): array
    {
        return [
            'tests_passed' => [
                'type' => 'boolean',
                'description' => 'Whether all tests passed',
            ],
            'coverage' => [
                'type' => 'number',
                'description' => 'Code coverage percentage',
            ],
        ];
    }

    protected function perform(ProjectContext $context): StepResultInterface
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return $this->failure('No active feature set in context');
        }

        $testContractPath = $feature->document('test-contract');

        return $this->needsReview(
            'Verification needed: run tests and validate against test contracts.',
            outputs: [
                'test_contract_path' => $testContractPath,
            ],
            metadata: [
                'suggested_skills' => ['validate-tests', 'run-tests'],
                'verification_checklist' => [
                    'All unit tests pass',
                    'All feature tests pass',
                    'Tests match test contract specifications',
                    'Code coverage meets minimum threshold',
                    'No regression in existing tests',
                ],
            ]
        );
    }

    public function canExecute(ProjectContext $context): bool
    {
        // Can execute after implementation
        return $context->get('implementation_complete', false) === true;
    }

    public function isComplete(ProjectContext $context): bool
    {
        return $context->get('verification_complete', false) === true
            && $context->get('tests_passed', false) === true;
    }
}
