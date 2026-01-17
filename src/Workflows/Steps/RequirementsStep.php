<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Steps;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Contracts\StepResultInterface;
use LaraForge\Workflows\Step;

class RequirementsStep extends Step
{
    public function identifier(): string
    {
        return 'requirements';
    }

    public function name(): string
    {
        return 'Requirements Gathering';
    }

    public function description(): string
    {
        return 'Create PRD and FRD documents with detailed requirements and acceptance criteria';
    }

    public function agentRole(): string
    {
        return 'analyst';
    }

    public function skills(): array
    {
        return ['create-prd', 'create-frd'];
    }

    public function requiredInputs(): array
    {
        return [
            'feature_title' => [
                'type' => 'string',
                'description' => 'The title/name of the feature',
                'required' => true,
            ],
            'feature_description' => [
                'type' => 'string',
                'description' => 'Brief description of the feature',
                'required' => false,
            ],
        ];
    }

    public function expectedOutputs(): array
    {
        return [
            'prd_path' => [
                'type' => 'string',
                'description' => 'Path to the generated PRD document',
            ],
            'frd_path' => [
                'type' => 'string',
                'description' => 'Path to the generated FRD document',
            ],
        ];
    }

    protected function perform(ProjectContext $context): StepResultInterface
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return $this->failure('No active feature set in context');
        }

        // Check if documents already exist
        $prdPath = $feature->document('prd');
        $frdPath = $feature->document('frd');

        if ($prdPath && $frdPath) {
            return $this->success(
                outputs: [
                    'prd_path' => $prdPath,
                    'frd_path' => $frdPath,
                ],
                metadata: ['skipped' => true, 'reason' => 'Documents already exist']
            );
        }

        // This step typically needs human/agent interaction to create documents
        return $this->needsReview(
            'PRD and/or FRD documents need to be created. Use create-prd and create-frd skills.',
            outputs: [
                'needs_prd' => ! $prdPath,
                'needs_frd' => ! $frdPath,
            ],
            metadata: [
                'suggested_skills' => array_filter([
                    ! $prdPath ? 'create-prd' : null,
                    ! $frdPath ? 'create-frd' : null,
                ]),
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

        if (! $feature) {
            return false;
        }

        return $feature->document('prd') !== null
            && $feature->document('frd') !== null;
    }
}
