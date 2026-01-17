<?php

declare(strict_types=1);

namespace LaraForge\Workflows;

use LaraForge\Workflows\Contracts\StepInterface;
use LaraForge\Workflows\Contracts\StepResultInterface;

final class StepResult implements StepResultInterface
{
    /**
     * @param  array<string, mixed>  $outputs
     * @param  array<string, mixed>  $artifacts
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly bool $success,
        private readonly StepInterface $step,
        private readonly array $outputs = [],
        private readonly array $artifacts = [],
        private readonly ?string $error = null,
        private readonly bool $needsReview = false,
        private readonly ?string $reviewNotes = null,
        private readonly array $metadata = [],
    ) {}

    public static function success(
        StepInterface $step,
        array $outputs = [],
        array $artifacts = [],
        bool $needsReview = false,
        ?string $reviewNotes = null,
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            step: $step,
            outputs: $outputs,
            artifacts: $artifacts,
            needsReview: $needsReview,
            reviewNotes: $reviewNotes,
            metadata: $metadata,
        );
    }

    public static function failure(
        StepInterface $step,
        string $error,
        array $outputs = [],
        array $artifacts = [],
        array $metadata = [],
    ): self {
        return new self(
            success: false,
            step: $step,
            outputs: $outputs,
            artifacts: $artifacts,
            error: $error,
            metadata: $metadata,
        );
    }

    public static function forReview(
        StepInterface $step,
        string $reviewNotes,
        array $outputs = [],
        array $artifacts = [],
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            step: $step,
            outputs: $outputs,
            artifacts: $artifacts,
            needsReview: true,
            reviewNotes: $reviewNotes,
            metadata: $metadata,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function step(): StepInterface
    {
        return $this->step;
    }

    public function outputs(): array
    {
        return $this->outputs;
    }

    public function artifacts(): array
    {
        return $this->artifacts;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function needsReview(): bool
    {
        return $this->needsReview;
    }

    public function reviewNotes(): ?string
    {
        return $this->reviewNotes;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function getOutput(string $key, mixed $default = null): mixed
    {
        return $this->outputs[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'step' => $this->step->identifier(),
            'outputs' => $this->outputs,
            'artifacts' => $this->artifacts,
            'error' => $this->error,
            'needs_review' => $this->needsReview,
            'review_notes' => $this->reviewNotes,
            'metadata' => $this->metadata,
        ];
    }
}
