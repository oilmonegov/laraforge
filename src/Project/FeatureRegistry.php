<?php

declare(strict_types=1);

namespace LaraForge\Project;

use LaraForge\Project\Contracts\FeatureInterface;
use LaraForge\Project\Contracts\ProjectStateInterface;

class FeatureRegistry
{
    public function __construct(
        private readonly ProjectStateInterface $state,
    ) {}

    /**
     * Create and register a new feature.
     */
    public function create(string $title, string $description = '', int $priority = 3): Feature
    {
        $feature = Feature::create($title, $description, $priority);
        $this->state->addFeature($feature);

        return $feature;
    }

    /**
     * Get a feature by ID.
     */
    public function get(string $id): ?FeatureInterface
    {
        return $this->state->feature($id);
    }

    /**
     * Get all features.
     *
     * @return array<FeatureInterface>
     */
    public function all(): array
    {
        return $this->state->features();
    }

    /**
     * Find features by status.
     *
     * @return array<FeatureInterface>
     */
    public function byStatus(string $status): array
    {
        return $this->state->featuresByStatus($status);
    }

    /**
     * Find features by tag.
     *
     * @return array<FeatureInterface>
     */
    public function byTag(string $tag): array
    {
        return array_filter(
            $this->state->features(),
            fn (FeatureInterface $f) => in_array($tag, $f->tags(), true)
        );
    }

    /**
     * Find features by assignee.
     *
     * @return array<FeatureInterface>
     */
    public function byAssignee(string $agentId): array
    {
        return array_filter(
            $this->state->features(),
            fn (FeatureInterface $f) => $f->assignee() === $agentId
        );
    }

    /**
     * Find features by priority.
     *
     * @return array<FeatureInterface>
     */
    public function byPriority(int $priority): array
    {
        return array_filter(
            $this->state->features(),
            fn (FeatureInterface $f) => $f->priority() === $priority
        );
    }

    /**
     * Search features by title or description.
     *
     * @return array<FeatureInterface>
     */
    public function search(string $query): array
    {
        $query = strtolower($query);

        return array_filter(
            $this->state->features(),
            fn (FeatureInterface $f) => str_contains(strtolower($f->title()), $query)
                || str_contains(strtolower($f->description()), $query)
        );
    }

    /**
     * Get active features (in progress).
     *
     * @return array<FeatureInterface>
     */
    public function active(): array
    {
        return $this->byStatus('in_progress');
    }

    /**
     * Get completed features.
     *
     * @return array<FeatureInterface>
     */
    public function completed(): array
    {
        return $this->byStatus('completed');
    }

    /**
     * Get high priority features (priority 1 or 2).
     *
     * @return array<FeatureInterface>
     */
    public function highPriority(): array
    {
        return array_filter(
            $this->state->features(),
            fn (FeatureInterface $f) => $f->priority() <= 2
        );
    }

    /**
     * Update a feature.
     */
    public function update(FeatureInterface $feature): void
    {
        $this->state->updateFeature($feature);
    }

    /**
     * Delete a feature.
     */
    public function delete(string $id): void
    {
        if ($this->state instanceof ProjectState) {
            $this->state->removeFeature($id);
        }
    }

    /**
     * Get the current/active feature.
     */
    public function current(): ?FeatureInterface
    {
        return $this->state->currentFeature();
    }

    /**
     * Set the current feature.
     */
    public function setCurrent(string $id): void
    {
        $this->state->setCurrentFeature($id);
    }

    /**
     * Get feature statistics.
     *
     * @return array{total: int, by_status: array<string, int>, by_priority: array<int, int>}
     */
    public function statistics(): array
    {
        $features = $this->state->features();

        $byStatus = [];
        $byPriority = [];

        foreach ($features as $feature) {
            $status = $feature->status();
            $priority = $feature->priority();

            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $byPriority[$priority] = ($byPriority[$priority] ?? 0) + 1;
        }

        return [
            'total' => count($features),
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
        ];
    }

    /**
     * Get recently updated features.
     *
     * @return array<FeatureInterface>
     */
    public function recentlyUpdated(int $limit = 5): array
    {
        $features = $this->state->features();

        usort($features, fn ($a, $b) => $b->updatedAt() <=> $a->updatedAt());

        return array_slice($features, 0, $limit);
    }
}
