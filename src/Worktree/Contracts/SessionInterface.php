<?php

declare(strict_types=1);

namespace LaraForge\Worktree\Contracts;

interface SessionInterface
{
    /**
     * Get the unique session identifier.
     */
    public function id(): string;

    /**
     * Get the worktree path.
     */
    public function path(): string;

    /**
     * Get the branch name.
     */
    public function branch(): string;

    /**
     * Get the associated feature identifier.
     */
    public function featureId(): string;

    /**
     * Get the agent identifier working in this session.
     */
    public function agentId(): string;

    /**
     * Get the session status (active, paused, completed, merged, abandoned).
     */
    public function status(): string;

    /**
     * Get the session creation timestamp.
     */
    public function createdAt(): \DateTimeInterface;

    /**
     * Get the last activity timestamp.
     */
    public function lastActivityAt(): \DateTimeInterface;

    /**
     * Check if the session is active.
     */
    public function isActive(): bool;

    /**
     * Get the list of modified files in this session.
     *
     * @return array<string>
     */
    public function modifiedFiles(): array;

    /**
     * Get the list of commits in this session.
     *
     * @return array<array{hash: string, message: string, timestamp: string}>
     */
    public function commits(): array;

    /**
     * Get session metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;

    /**
     * Convert the session to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
