<?php

declare(strict_types=1);

namespace LaraForge\Worktree;

use LaraForge\Worktree\Contracts\SessionInterface;

class AgentSession implements SessionInterface
{
    private \DateTimeInterface $createdAt;

    private \DateTimeInterface $lastActivityAt;

    /**
     * @param  array<string>  $modifiedFiles
     * @param  array<array{hash: string, message: string, timestamp: string}>  $commits
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $id,
        private readonly string $path,
        private readonly string $branch,
        private readonly string $featureId,
        private readonly string $agentId,
        private string $status = 'active',
        private array $modifiedFiles = [],
        private array $commits = [],
        private array $metadata = [],
        ?\DateTimeInterface $createdAt = null,
        ?\DateTimeInterface $lastActivityAt = null,
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable;
        $this->lastActivityAt = $lastActivityAt ?? new \DateTimeImmutable;
    }

    public static function create(
        string $path,
        string $branch,
        string $featureId,
        string $agentId,
    ): self {
        return new self(
            id: self::generateId($featureId, $agentId),
            path: $path,
            branch: $branch,
            featureId: $featureId,
            agentId: $agentId,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function branch(): string
    {
        return $this->branch;
    }

    public function featureId(): string
    {
        return $this->featureId;
    }

    public function agentId(): string
    {
        return $this->agentId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function lastActivityAt(): \DateTimeInterface
    {
        return $this->lastActivityAt;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function modifiedFiles(): array
    {
        return $this->modifiedFiles;
    }

    public function commits(): array
    {
        return $this->commits;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function addModifiedFile(string $file): void
    {
        if (! in_array($file, $this->modifiedFiles, true)) {
            $this->modifiedFiles[] = $file;
        }
        $this->touch();
    }

    public function addCommit(string $hash, string $message): void
    {
        $this->commits[] = [
            'hash' => $hash,
            'message' => $message,
            'timestamp' => (new \DateTimeImmutable)->format('c'),
        ];
        $this->touch();
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function touch(): void
    {
        $this->lastActivityAt = new \DateTimeImmutable;
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isMerged(): bool
    {
        return $this->status === 'merged';
    }

    public function isAbandoned(): bool
    {
        return $this->status === 'abandoned';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'branch' => $this->branch,
            'feature_id' => $this->featureId,
            'agent_id' => $this->agentId,
            'status' => $this->status,
            'modified_files' => $this->modifiedFiles,
            'commits' => $this->commits,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('c'),
            'last_activity_at' => $this->lastActivityAt->format('c'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            path: $data['path'],
            branch: $data['branch'],
            featureId: $data['feature_id'],
            agentId: $data['agent_id'],
            status: $data['status'] ?? 'active',
            modifiedFiles: $data['modified_files'] ?? [],
            commits: $data['commits'] ?? [],
            metadata: $data['metadata'] ?? [],
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            lastActivityAt: isset($data['last_activity_at']) ? new \DateTimeImmutable($data['last_activity_at']) : null,
        );
    }

    private static function generateId(string $featureId, string $agentId): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9]/', '-', $featureId.'-'.$agentId) ?? '';
        $slug = strtolower(trim($slug, '-'));

        return $slug.'-'.substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
