<?php

declare(strict_types=1);

namespace LaraForge\Worktree;

use LaraForge\Worktree\Contracts\ConflictInterface;
use LaraForge\Worktree\Contracts\MergeResultInterface;

class MergeResult implements MergeResultInterface
{
    /**
     * @param  array<string>  $sourceBranches
     * @param  array<ConflictInterface>  $conflicts
     * @param  array<string>  $mergedFiles
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly bool $success,
        private readonly string $targetBranch,
        private readonly array $sourceBranches,
        private readonly ?string $commitHash = null,
        private readonly array $conflicts = [],
        private readonly ?string $error = null,
        private readonly array $mergedFiles = [],
        private readonly array $metadata = [],
    ) {}

    public static function success(
        string $targetBranch,
        array $sourceBranches,
        string $commitHash,
        array $mergedFiles = [],
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            targetBranch: $targetBranch,
            sourceBranches: $sourceBranches,
            commitHash: $commitHash,
            mergedFiles: $mergedFiles,
            metadata: $metadata,
        );
    }

    public static function failure(
        string $targetBranch,
        array $sourceBranches,
        string $error,
        array $conflicts = [],
        array $metadata = [],
    ): self {
        return new self(
            success: false,
            targetBranch: $targetBranch,
            sourceBranches: $sourceBranches,
            conflicts: $conflicts,
            error: $error,
            metadata: $metadata,
        );
    }

    public static function conflict(
        string $targetBranch,
        array $sourceBranches,
        array $conflicts,
        array $metadata = [],
    ): self {
        return new self(
            success: false,
            targetBranch: $targetBranch,
            sourceBranches: $sourceBranches,
            conflicts: $conflicts,
            error: 'Merge conflicts detected',
            metadata: $metadata,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function commitHash(): ?string
    {
        return $this->commitHash;
    }

    public function targetBranch(): string
    {
        return $this->targetBranch;
    }

    public function sourceBranches(): array
    {
        return $this->sourceBranches;
    }

    public function hasConflicts(): bool
    {
        return ! empty($this->conflicts);
    }

    public function conflicts(): array
    {
        return $this->conflicts;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function mergedFiles(): array
    {
        return $this->mergedFiles;
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
            'target_branch' => $this->targetBranch,
            'source_branches' => $this->sourceBranches,
            'commit_hash' => $this->commitHash,
            'has_conflicts' => $this->hasConflicts(),
            'conflicts' => array_map(
                fn (ConflictInterface $c) => $c instanceof Conflict ? $c->toArray() : ['file' => $c->filePath()],
                $this->conflicts
            ),
            'error' => $this->error,
            'merged_files' => $this->mergedFiles,
            'metadata' => $this->metadata,
        ];
    }
}
