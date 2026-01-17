<?php

declare(strict_types=1);

namespace LaraForge\Worktree;

use LaraForge\Worktree\Contracts\ConflictInterface;

class Conflict implements ConflictInterface
{
    /**
     * @param  array<string>  $sessionIds
     * @param  array<string>  $branches
     * @param  array<array{branch: string, content: string}>  $sections
     * @param  array<array{strategy: string, description: string}>  $suggestedResolutions
     */
    public function __construct(
        private readonly string $filePath,
        private readonly string $type,
        private readonly array $sessionIds,
        private readonly array $branches,
        private readonly array $sections = [],
        private readonly string $description = '',
        private readonly array $suggestedResolutions = [],
        private readonly bool $canAutoResolve = false,
        private readonly ?string $autoResolution = null,
    ) {}

    public static function content(
        string $filePath,
        array $branches,
        array $sections,
        array $sessionIds = [],
    ): self {
        return new self(
            filePath: $filePath,
            type: 'content',
            sessionIds: $sessionIds,
            branches: $branches,
            sections: $sections,
            description: "Content conflict in {$filePath}",
            suggestedResolutions: [
                ['strategy' => 'ours', 'description' => 'Keep changes from current branch'],
                ['strategy' => 'theirs', 'description' => 'Keep changes from incoming branch'],
                ['strategy' => 'manual', 'description' => 'Manually resolve the conflict'],
            ],
        );
    }

    public static function rename(
        string $filePath,
        array $branches,
        array $sessionIds = [],
    ): self {
        return new self(
            filePath: $filePath,
            type: 'rename',
            sessionIds: $sessionIds,
            branches: $branches,
            description: "Rename conflict for {$filePath}",
            suggestedResolutions: [
                ['strategy' => 'ours', 'description' => 'Keep name from current branch'],
                ['strategy' => 'theirs', 'description' => 'Keep name from incoming branch'],
            ],
        );
    }

    public static function delete(
        string $filePath,
        array $branches,
        array $sessionIds = [],
    ): self {
        return new self(
            filePath: $filePath,
            type: 'delete',
            sessionIds: $sessionIds,
            branches: $branches,
            description: "Delete conflict for {$filePath} - modified in one branch, deleted in another",
            suggestedResolutions: [
                ['strategy' => 'keep', 'description' => 'Keep the file with modifications'],
                ['strategy' => 'delete', 'description' => 'Delete the file'],
            ],
        );
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function sessionIds(): array
    {
        return $this->sessionIds;
    }

    public function branches(): array
    {
        return $this->branches;
    }

    public function sections(): array
    {
        return $this->sections;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function suggestedResolutions(): array
    {
        return $this->suggestedResolutions;
    }

    public function canAutoResolve(): bool
    {
        return $this->canAutoResolve;
    }

    public function autoResolution(): ?string
    {
        return $this->autoResolution;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'type' => $this->type,
            'session_ids' => $this->sessionIds,
            'branches' => $this->branches,
            'sections' => $this->sections,
            'description' => $this->description,
            'suggested_resolutions' => $this->suggestedResolutions,
            'can_auto_resolve' => $this->canAutoResolve,
            'auto_resolution' => $this->autoResolution,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filePath: $data['file_path'],
            type: $data['type'],
            sessionIds: $data['session_ids'] ?? [],
            branches: $data['branches'] ?? [],
            sections: $data['sections'] ?? [],
            description: $data['description'] ?? '',
            suggestedResolutions: $data['suggested_resolutions'] ?? [],
            canAutoResolve: $data['can_auto_resolve'] ?? false,
            autoResolution: $data['auto_resolution'] ?? null,
        );
    }
}
