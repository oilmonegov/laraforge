<?php

declare(strict_types=1);

namespace LaraForge\Worktree\Contracts;

interface WorktreeManagerInterface
{
    /**
     * Create a new worktree session for parallel work.
     */
    public function createSession(string $featureId, string $agentId, ?string $baseBranch = null): SessionInterface;

    /**
     * Get a session by ID.
     */
    public function getSession(string $sessionId): ?SessionInterface;

    /**
     * Get all active sessions.
     *
     * @return array<SessionInterface>
     */
    public function activeSessions(): array;

    /**
     * Get sessions for a specific feature.
     *
     * @return array<SessionInterface>
     */
    public function sessionsForFeature(string $featureId): array;

    /**
     * Get sessions for a specific agent.
     *
     * @return array<SessionInterface>
     */
    public function sessionsForAgent(string $agentId): array;

    /**
     * Pause a session (agent can resume later).
     */
    public function pauseSession(string $sessionId): void;

    /**
     * Resume a paused session.
     */
    public function resumeSession(string $sessionId): SessionInterface;

    /**
     * Complete a session (ready for merge).
     */
    public function completeSession(string $sessionId): void;

    /**
     * Abandon a session (discard changes).
     */
    public function abandonSession(string $sessionId): void;

    /**
     * Merge a completed session back to the target branch.
     */
    public function mergeSession(string $sessionId, ?string $targetBranch = null): MergeResultInterface;

    /**
     * Merge multiple sessions together.
     *
     * @param  array<string>  $sessionIds
     */
    public function mergeSessions(array $sessionIds, ?string $targetBranch = null): MergeResultInterface;

    /**
     * Check for conflicts between sessions.
     *
     * @param  array<string>  $sessionIds
     * @return array<ConflictInterface>
     */
    public function detectConflicts(array $sessionIds): array;

    /**
     * Clean up old/stale worktrees.
     */
    public function cleanup(int $olderThanDays = 7): int;

    /**
     * List all worktrees (including non-LaraForge ones).
     *
     * @return array<array{path: string, branch: string, head: string}>
     */
    public function listWorktrees(): array;
}
