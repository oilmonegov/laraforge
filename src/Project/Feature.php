<?php

declare(strict_types=1);

namespace LaraForge\Project;

use LaraForge\Project\Contracts\FeatureInterface;

class Feature implements FeatureInterface
{
    private \DateTimeInterface $createdAt;

    private \DateTimeInterface $updatedAt;

    /**
     * @param  array<string, string>  $documents
     * @param  array<string>  $tags
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $id,
        private string $title,
        private string $description = '',
        private string $status = 'planning',
        private string $phase = 'new',
        private ?string $branch = null,
        private ?string $assignee = null,
        private int $progress = 0,
        private array $documents = [],
        private int $priority = 3,
        private array $tags = [],
        private array $metadata = [],
        ?\DateTimeInterface $createdAt = null,
        ?\DateTimeInterface $updatedAt = null,
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable;
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable;
    }

    public static function create(string $title, string $description = '', int $priority = 3): self
    {
        return new self(
            id: self::generateId($title),
            title: $title,
            description: $description,
            priority: $priority,
        );
    }

    public function id(): string
    {
        return $this->id;
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

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function setPhase(string $phase): void
    {
        $this->phase = $phase;
        $this->updateProgressFromPhase($phase);
        $this->touch();
    }

    public function branch(): ?string
    {
        return $this->branch;
    }

    public function setBranch(string $branch): void
    {
        $this->branch = $branch;
        $this->touch();
    }

    public function assignee(): ?string
    {
        return $this->assignee;
    }

    public function setAssignee(string $agentId): void
    {
        $this->assignee = $agentId;
        $this->touch();
    }

    public function progress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): void
    {
        $this->progress = max(0, min(100, $progress));
        $this->touch();
    }

    public function documents(): array
    {
        return $this->documents;
    }

    public function addDocument(string $type, string $path): void
    {
        $this->documents[$type] = $path;
        $this->touch();
    }

    public function document(string $type): ?string
    {
        return $this->documents[$type] ?? null;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = max(1, min(5, $priority));
        $this->touch();
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function addTag(string $tag): void
    {
        if (! in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
            $this->touch();
        }
    }

    public function removeTag(string $tag): void
    {
        $this->tags = array_values(array_filter($this->tags, fn ($t) => $t !== $tag));
        $this->touch();
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
        $this->touch();
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function createdAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->touch();
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'phase' => $this->phase,
            'branch' => $this->branch,
            'assignee' => $this->assignee,
            'progress' => $this->progress,
            'documents' => $this->documents,
            'priority' => $this->priority,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            description: $data['description'] ?? '',
            status: $data['status'] ?? 'planning',
            phase: $data['phase'] ?? 'new',
            branch: $data['branch'] ?? null,
            assignee: $data['assignee'] ?? null,
            progress: $data['progress'] ?? 0,
            documents: $data['documents'] ?? [],
            priority: $data['priority'] ?? 3,
            tags: $data['tags'] ?? [],
            metadata: $data['metadata'] ?? [],
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
        );
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable;
    }

    private function updateProgressFromPhase(string $phase): void
    {
        $phaseProgress = [
            'new' => 0,
            'planning' => 5,
            'requirements' => 15,
            'design' => 30,
            'implementation' => 50,
            'testing' => 75,
            'review' => 90,
            'completed' => 100,
        ];

        if (isset($phaseProgress[$phase])) {
            $this->progress = max($this->progress, $phaseProgress[$phase]);
        }
    }

    private static function generateId(string $title): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9\s]/', '', $title) ?? '';
        $slug = preg_replace('/\s+/', '-', trim($slug)) ?? '';
        $slug = strtolower($slug);

        return $slug.'-'.substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
