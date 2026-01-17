<?php

declare(strict_types=1);

namespace LaraForge\Project\Contracts;

interface ProjectStateInterface
{
    /**
     * Get the project name.
     */
    public function name(): string;

    /**
     * Get the project version.
     */
    public function version(): string;

    /**
     * Get the project root path.
     */
    public function rootPath(): string;

    /**
     * Get all registered features.
     *
     * @return array<FeatureInterface>
     */
    public function features(): array;

    /**
     * Get a feature by ID.
     */
    public function feature(string $id): ?FeatureInterface;

    /**
     * Get the current/active feature.
     */
    public function currentFeature(): ?FeatureInterface;

    /**
     * Set the current feature.
     */
    public function setCurrentFeature(string $featureId): void;

    /**
     * Add a new feature.
     */
    public function addFeature(FeatureInterface $feature): void;

    /**
     * Update a feature.
     */
    public function updateFeature(FeatureInterface $feature): void;

    /**
     * Get features by status.
     *
     * @return array<FeatureInterface>
     */
    public function featuresByStatus(string $status): array;

    /**
     * Get the backlog items.
     *
     * @return array<array{id: string, title: string, status: string, priority?: int}>
     */
    public function backlog(): array;

    /**
     * Add an item to the backlog.
     */
    public function addToBacklog(string $id, string $title, int $priority = 3): void;

    /**
     * Get active worktree sessions.
     *
     * @return array<\LaraForge\Worktree\Contracts\SessionInterface>
     */
    public function activeSessions(): array;

    /**
     * Get project configuration.
     *
     * @return array<string, mixed>
     */
    public function config(): array;

    /**
     * Get a configuration value.
     */
    public function getConfig(string $key, mixed $default = null): mixed;

    /**
     * Set a configuration value.
     */
    public function setConfig(string $key, mixed $value): void;

    /**
     * Save the project state to disk.
     */
    public function save(): void;

    /**
     * Reload the project state from disk.
     */
    public function reload(): void;

    /**
     * Get the state file path.
     */
    public function statePath(): string;

    /**
     * Convert the state to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
