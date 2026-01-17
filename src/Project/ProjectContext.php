<?php

declare(strict_types=1);

namespace LaraForge\Project;

use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Project\Contracts\FeatureInterface;
use LaraForge\Project\Contracts\ProjectStateInterface;

class ProjectContext
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly LaraForgeInterface $laraforge,
        private readonly ?ProjectStateInterface $state = null,
        private readonly ?FeatureInterface $feature = null,
        private array $data = [],
    ) {}

    public function laraforge(): LaraForgeInterface
    {
        return $this->laraforge;
    }

    public function state(): ?ProjectStateInterface
    {
        return $this->state;
    }

    public function currentFeature(): ?FeatureInterface
    {
        return $this->feature ?? $this->state?->currentFeature();
    }

    public function workingDirectory(): string
    {
        return $this->laraforge->workingDirectory();
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->laraforge->config()->get($key, $default);
    }

    public function hasFeature(): bool
    {
        return $this->currentFeature() !== null;
    }

    public function featurePhase(): ?string
    {
        return $this->currentFeature()?->phase();
    }

    public function featureStatus(): ?string
    {
        return $this->currentFeature()?->status();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function withFeature(FeatureInterface $feature): self
    {
        return new self(
            laraforge: $this->laraforge,
            state: $this->state,
            feature: $feature,
            data: $this->data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function withData(array $data): self
    {
        return new self(
            laraforge: $this->laraforge,
            state: $this->state,
            feature: $this->feature,
            data: array_merge($this->data, $data),
        );
    }

    public function laraforgeDir(): string
    {
        return $this->workingDirectory().'/.laraforge';
    }

    public function docsDir(): string
    {
        return $this->laraforgeDir().'/docs';
    }

    public function worktreesDir(): string
    {
        return $this->laraforgeDir().'/worktrees';
    }

    public function projectStatePath(): string
    {
        return $this->laraforgeDir().'/project.yaml';
    }
}
