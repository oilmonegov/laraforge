<?php

declare(strict_types=1);

namespace LaraForge\Worktree\Contracts;

interface MergeResultInterface
{
    /**
     * Check if the merge was successful.
     */
    public function isSuccess(): bool;

    /**
     * Get the merged commit hash.
     */
    public function commitHash(): ?string;

    /**
     * Get the target branch that was merged into.
     */
    public function targetBranch(): string;

    /**
     * Get the source branches that were merged.
     *
     * @return array<string>
     */
    public function sourceBranches(): array;

    /**
     * Check if there were conflicts.
     */
    public function hasConflicts(): bool;

    /**
     * Get the conflicts if any.
     *
     * @return array<ConflictInterface>
     */
    public function conflicts(): array;

    /**
     * Get the error message if merge failed.
     */
    public function error(): ?string;

    /**
     * Get the list of files that were merged.
     *
     * @return array<string>
     */
    public function mergedFiles(): array;

    /**
     * Get merge metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
