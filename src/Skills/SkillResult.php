<?php

declare(strict_types=1);

namespace LaraForge\Skills;

use LaraForge\Skills\Contracts\SkillResultInterface;

final class SkillResult implements SkillResultInterface
{
    /**
     * @param  array<string, mixed>  $artifacts
     * @param  array<array{skill: string, params?: array, reason?: string}>  $nextSteps
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly bool $success,
        private readonly mixed $output = null,
        private readonly array $artifacts = [],
        private readonly array $nextSteps = [],
        private readonly ?string $error = null,
        private readonly array $metadata = [],
    ) {}

    public static function success(
        mixed $output = null,
        array $artifacts = [],
        array $nextSteps = [],
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            output: $output,
            artifacts: $artifacts,
            nextSteps: $nextSteps,
            metadata: $metadata,
        );
    }

    public static function failure(
        string $error,
        mixed $output = null,
        array $artifacts = [],
        array $metadata = [],
    ): self {
        return new self(
            success: false,
            output: $output,
            artifacts: $artifacts,
            error: $error,
            metadata: $metadata,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function output(): mixed
    {
        return $this->output;
    }

    public function artifacts(): array
    {
        return $this->artifacts;
    }

    public function nextSteps(): array
    {
        return $this->nextSteps;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'artifacts' => $this->artifacts,
            'next_steps' => $this->nextSteps,
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }
}
