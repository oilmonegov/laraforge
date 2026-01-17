<?php

declare(strict_types=1);

namespace LaraForge\Agents;

use LaraForge\Agents\Contracts\TaskInterface;

class Task implements TaskInterface
{
    /**
     * @param  array<string, mixed>  $params
     * @param  array<string>  $subtaskIds
     * @param  array<string>  $dependencies
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly string $title,
        private readonly string $description = '',
        private string $status = 'pending',
        private readonly int $priority = 3,
        private readonly array $params = [],
        private readonly ?string $parentId = null,
        private array $subtaskIds = [],
        private readonly array $dependencies = [],
        private ?string $assignee = null,
        private readonly ?string $featureId = null,
        private array $metadata = [],
    ) {}

    public static function create(
        string $type,
        string $title,
        string $description = '',
        array $params = [],
        ?string $featureId = null,
        int $priority = 3,
    ): self {
        return new self(
            id: self::generateId(),
            type: $type,
            title: $title,
            description: $description,
            priority: $priority,
            params: $params,
            featureId: $featureId,
        );
    }

    public static function feature(string $title, string $description = '', array $params = []): self
    {
        return self::create('feature', $title, $description, $params);
    }

    public static function bugfix(string $title, string $description = '', array $params = []): self
    {
        return self::create('bugfix', $title, $description, $params);
    }

    public static function refactor(string $title, string $description = '', array $params = []): self
    {
        return self::create('refactor', $title, $description, $params);
    }

    public static function test(string $title, string $description = '', array $params = []): self
    {
        return self::create('test', $title, $description, $params);
    }

    public static function review(string $title, string $description = '', array $params = []): self
    {
        return self::create('review', $title, $description, $params);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function params(): array
    {
        return $this->params;
    }

    public function parentId(): ?string
    {
        return $this->parentId;
    }

    public function subtaskIds(): array
    {
        return $this->subtaskIds;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function assignee(): ?string
    {
        return $this->assignee;
    }

    public function featureId(): ?string
    {
        return $this->featureId;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setAssignee(string $agentId): void
    {
        $this->assignee = $agentId;
    }

    public function addSubtask(string $taskId): void
    {
        if (! in_array($taskId, $this->subtaskIds, true)) {
            $this->subtaskIds[] = $taskId;
        }
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function isBlocked(): bool
    {
        return ! empty($this->dependencies);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function withSubtask(string $title, string $description = '', array $params = []): self
    {
        $subtask = new self(
            id: self::generateId(),
            type: $this->type,
            title: $title,
            description: $description,
            params: $params,
            parentId: $this->id,
            featureId: $this->featureId,
        );

        $this->addSubtask($subtask->id());

        return $subtask;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'params' => $this->params,
            'parent_id' => $this->parentId,
            'subtask_ids' => $this->subtaskIds,
            'dependencies' => $this->dependencies,
            'assignee' => $this->assignee,
            'feature_id' => $this->featureId,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            title: $data['title'],
            description: $data['description'] ?? '',
            status: $data['status'] ?? 'pending',
            priority: $data['priority'] ?? 3,
            params: $data['params'] ?? [],
            parentId: $data['parent_id'] ?? null,
            subtaskIds: $data['subtask_ids'] ?? [],
            dependencies: $data['dependencies'] ?? [],
            assignee: $data['assignee'] ?? null,
            featureId: $data['feature_id'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    private static function generateId(): string
    {
        return 'task_'.bin2hex(random_bytes(8));
    }
}
