<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Steps;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Contracts\StepResultInterface;
use LaraForge\Workflows\Step;

class DesignStep extends Step
{
    public function identifier(): string
    {
        return 'design';
    }

    public function name(): string
    {
        return 'Design & Architecture';
    }

    public function description(): string
    {
        return 'Create design document with pseudocode and architectural decisions';
    }

    public function agentRole(): string
    {
        return 'architect';
    }

    public function skills(): array
    {
        return ['create-pseudocode', 'create-design'];
    }

    public function dependencies(): array
    {
        return ['requirements'];
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
            'design_path' => [
                'type' => 'string',
                'description' => 'Path to the generated design document',
            ],
        ];
    }

    protected function perform(ProjectContext $context): StepResultInterface
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return $this->failure('No active feature set in context');
        }

        // Check if design document already exists
        $designPath = $feature->document('design');

        if ($designPath) {
            return $this->success(
                outputs: ['design_path' => $designPath],
                metadata: ['skipped' => true, 'reason' => 'Design document already exists']
            );
        }

        // Ensure FRD exists
        $frdPath = $feature->document('frd');
        if (! $frdPath) {
            return $this->failure('FRD document is required before design');
        }

        return $this->needsReview(
            'Design document needs to be created with pseudocode. Use create-pseudocode skill.',
            outputs: [
                'frd_path' => $frdPath,
            ],
            metadata: [
                'suggested_skills' => ['create-pseudocode'],
            ]
        );
    }

    public function canExecute(ProjectContext $context): bool
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return false;
        }

        // Need FRD to exist
        return $feature->document('frd') !== null;
    }

    public function isComplete(ProjectContext $context): bool
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return false;
        }

        return $feature->document('design') !== null;
    }
}
