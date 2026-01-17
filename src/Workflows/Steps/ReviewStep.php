<?php

declare(strict_types=1);

namespace LaraForge\Workflows\Steps;

use LaraForge\Project\ProjectContext;
use LaraForge\Workflows\Contracts\StepResultInterface;
use LaraForge\Workflows\Step;

class ReviewStep extends Step
{
    public function identifier(): string
    {
        return 'review';
    }

    public function name(): string
    {
        return 'Code Review';
    }

    public function description(): string
    {
        return 'Review implementation for quality, adherence to design, and best practices';
    }

    public function agentRole(): string
    {
        return 'reviewer';
    }

    public function skills(): array
    {
        return ['review-code', 'suggest-improvements'];
    }

    public function dependencies(): array
    {
        return ['verify'];
    }

    public function expectedOutputs(): array
    {
        return [
            'review_approved' => [
                'type' => 'boolean',
                'description' => 'Whether the review was approved',
            ],
            'review_comments' => [
                'type' => 'array',
                'description' => 'List of review comments/suggestions',
            ],
        ];
    }

    protected function perform(ProjectContext $context): StepResultInterface
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return $this->failure('No active feature set in context');
        }

        $documents = [
            'frd' => $feature->document('frd'),
            'design' => $feature->document('design'),
            'test_contract' => $feature->document('test-contract'),
        ];

        return $this->needsReview(
            'Code review required before merge.',
            outputs: $documents,
            metadata: [
                'suggested_skills' => ['review-code'],
                'review_checklist' => [
                    'Code follows project conventions',
                    'Implementation matches design document',
                    'No security vulnerabilities',
                    'Proper error handling',
                    'Adequate test coverage',
                    'Documentation is complete',
                    'No unnecessary complexity',
                ],
            ]
        );
    }

    public function canExecute(ProjectContext $context): bool
    {
        return $context->get('verification_complete', false) === true;
    }

    public function isComplete(ProjectContext $context): bool
    {
        return $context->get('review_approved', false) === true;
    }
}
