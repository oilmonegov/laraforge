<?php

declare(strict_types=1);

namespace LaraForge\Agents;

use LaraForge\Agents\Contracts\AgentResultInterface;
use LaraForge\Agents\Contracts\TaskInterface;
use LaraForge\Skills\Contracts\SkillResultInterface;

class AgentResult implements AgentResultInterface
{
    /**
     * @param  array<string, mixed>  $artifacts
     * @param  array<SkillResultInterface>  $skillResults
     * @param  array<array{type: string, title: string, description?: string, params?: array}>  $nextTasks
     * @param  array{agent: string, reason: string, data: array}|null  $handoff
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly bool $success,
        private readonly TaskInterface $task,
        private readonly mixed $output = null,
        private readonly array $artifacts = [],
        private readonly array $skillResults = [],
        private readonly array $nextTasks = [],
        private readonly ?string $error = null,
        private readonly ?array $handoff = null,
        private readonly array $metadata = [],
    ) {}

    public static function success(
        TaskInterface $task,
        mixed $output = null,
        array $artifacts = [],
        array $skillResults = [],
        array $nextTasks = [],
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            task: $task,
            output: $output,
            artifacts: $artifacts,
            skillResults: $skillResults,
            nextTasks: $nextTasks,
            metadata: $metadata,
        );
    }

    public static function failure(
        TaskInterface $task,
        string $error,
        mixed $output = null,
        array $artifacts = [],
        array $skillResults = [],
        array $metadata = [],
    ): self {
        return new self(
            success: false,
            task: $task,
            output: $output,
            artifacts: $artifacts,
            skillResults: $skillResults,
            error: $error,
            metadata: $metadata,
        );
    }

    public static function withHandoff(
        TaskInterface $task,
        string $toAgent,
        string $reason,
        array $data = [],
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            task: $task,
            handoff: [
                'agent' => $toAgent,
                'reason' => $reason,
                'data' => $data,
            ],
            metadata: $metadata,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function task(): TaskInterface
    {
        return $this->task;
    }

    public function output(): mixed
    {
        return $this->output;
    }

    public function artifacts(): array
    {
        return $this->artifacts;
    }

    public function skillResults(): array
    {
        return $this->skillResults;
    }

    public function nextTasks(): array
    {
        return $this->nextTasks;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function handoff(): ?array
    {
        return $this->handoff;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function hasHandoff(): bool
    {
        return $this->handoff !== null;
    }

    public function hasNextTasks(): bool
    {
        return ! empty($this->nextTasks);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'task' => $this->task->toArray(),
            'output' => $this->output,
            'artifacts' => $this->artifacts,
            'skill_results' => array_map(
                fn (SkillResultInterface $result) => $result instanceof \LaraForge\Skills\SkillResult
                    ? $result->toArray()
                    : ['success' => $result->isSuccess()],
                $this->skillResults
            ),
            'next_tasks' => $this->nextTasks,
            'error' => $this->error,
            'handoff' => $this->handoff,
            'metadata' => $this->metadata,
        ];
    }
}
