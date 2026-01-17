<?php

declare(strict_types=1);

namespace LaraForge\Project;

use LaraForge\Project\Contracts\FeatureInterface;
use LaraForge\Project\Contracts\ProjectStateInterface;
use LaraForge\Workflows\Contracts\WorkflowInterface;

class ProgressTracker
{
    public function __construct(
        private readonly ProjectStateInterface $state,
    ) {}

    /**
     * Update feature progress based on workflow status.
     */
    public function updateFromWorkflow(FeatureInterface $feature, WorkflowInterface $workflow, ProjectContext $context): void
    {
        if (! $feature instanceof Feature) {
            return;
        }

        $completedSteps = $context->get('completed_steps', []);
        $totalSteps = count($workflow->steps());
        $completedCount = count($completedSteps);

        $progress = $totalSteps > 0 ? (int) round(($completedCount / $totalSteps) * 100) : 0;
        $feature->setProgress($progress);

        // Update phase based on current step
        $currentStep = $workflow->currentStep($context);
        if ($currentStep) {
            $this->updatePhaseFromStep($feature, $currentStep->identifier());
        } elseif ($workflow->isComplete($context)) {
            $feature->setPhase('completed');
            $feature->setStatus('completed');
        }

        $this->state->updateFeature($feature);
    }

    /**
     * Update feature phase from step identifier.
     */
    public function updatePhaseFromStep(Feature $feature, string $stepId): void
    {
        $phaseMapping = [
            'requirements' => 'requirements',
            'design' => 'design',
            'branch' => 'implementation',
            'test-contract' => 'design',
            'implement' => 'implementation',
            'verify' => 'testing',
            'review' => 'review',
            'merge' => 'review',
        ];

        $phase = $phaseMapping[$stepId] ?? $feature->phase();
        $feature->setPhase($phase);
    }

    /**
     * Get overall project progress.
     *
     * @return array{total_features: int, by_status: array, average_progress: int}
     */
    public function projectProgress(): array
    {
        $features = $this->state->features();
        $total = count($features);

        $byStatus = [
            'planning' => 0,
            'in_progress' => 0,
            'testing' => 0,
            'review' => 0,
            'completed' => 0,
        ];

        $totalProgress = 0;

        foreach ($features as $feature) {
            $status = $feature->status();
            if (isset($byStatus[$status])) {
                $byStatus[$status]++;
            }
            $totalProgress += $feature->progress();
        }

        return [
            'total_features' => $total,
            'by_status' => $byStatus,
            'average_progress' => $total > 0 ? (int) round($totalProgress / $total) : 0,
        ];
    }

    /**
     * Get feature summary for display.
     *
     * @return array<array{id: string, title: string, status: string, phase: string, progress: int, assignee: ?string}>
     */
    public function featureSummary(): array
    {
        $summary = [];

        foreach ($this->state->features() as $feature) {
            $summary[] = [
                'id' => $feature->id(),
                'title' => $feature->title(),
                'status' => $feature->status(),
                'phase' => $feature->phase(),
                'progress' => $feature->progress(),
                'assignee' => $feature->assignee(),
            ];
        }

        // Sort by status (in_progress first) then by priority
        usort($summary, function ($a, $b) {
            $statusOrder = ['in_progress' => 0, 'testing' => 1, 'review' => 2, 'planning' => 3, 'completed' => 4];
            $aOrder = $statusOrder[$a['status']] ?? 5;
            $bOrder = $statusOrder[$b['status']] ?? 5;

            if ($aOrder !== $bOrder) {
                return $aOrder <=> $bOrder;
            }

            return $b['progress'] <=> $a['progress'];
        });

        return $summary;
    }

    /**
     * Get features that need attention (blocked, stale, etc.).
     *
     * @return array<array{feature: FeatureInterface, reason: string}>
     */
    public function needsAttention(): array
    {
        $attention = [];
        $staleThreshold = new \DateTimeImmutable('-7 days');

        foreach ($this->state->features() as $feature) {
            // Skip completed features
            if ($feature->status() === 'completed') {
                continue;
            }

            // Check for stale features
            if ($feature->updatedAt() < $staleThreshold) {
                $attention[] = [
                    'feature' => $feature,
                    'reason' => 'No activity in over 7 days',
                ];

                continue;
            }

            // Check for features without assignee
            if ($feature->status() === 'in_progress' && ! $feature->assignee()) {
                $attention[] = [
                    'feature' => $feature,
                    'reason' => 'In progress but no assignee',
                ];

                continue;
            }

            // Check for features in planning too long
            if ($feature->phase() === 'planning' && $feature->status() !== 'planning') {
                $attention[] = [
                    'feature' => $feature,
                    'reason' => 'Status mismatch: marked active but still in planning phase',
                ];
            }
        }

        return $attention;
    }

    /**
     * Generate a status board (kanban-style).
     *
     * @return array<string, array<FeatureInterface>>
     */
    public function statusBoard(): array
    {
        $board = [
            'backlog' => [],
            'planning' => [],
            'in_progress' => [],
            'testing' => [],
            'review' => [],
            'completed' => [],
        ];

        // Add backlog items
        foreach ($this->state->backlog() as $item) {
            $board['backlog'][] = $item;
        }

        // Add features
        foreach ($this->state->features() as $feature) {
            $status = $feature->status();
            if (isset($board[$status])) {
                $board[$status][] = $feature;
            } else {
                $board['in_progress'][] = $feature;
            }
        }

        return $board;
    }

    /**
     * Mark a feature as started.
     */
    public function startFeature(string $featureId, ?string $agentId = null): void
    {
        $feature = $this->state->feature($featureId);
        if (! $feature instanceof Feature) {
            return;
        }

        $feature->setStatus('in_progress');
        if ($agentId) {
            $feature->setAssignee($agentId);
        }

        $this->state->updateFeature($feature);
        $this->state->setCurrentFeature($featureId);
    }

    /**
     * Mark a feature as complete.
     */
    public function completeFeature(string $featureId): void
    {
        $feature = $this->state->feature($featureId);
        if (! $feature instanceof Feature) {
            return;
        }

        $feature->setStatus('completed');
        $feature->setPhase('completed');
        $feature->setProgress(100);

        $this->state->updateFeature($feature);
    }
}
