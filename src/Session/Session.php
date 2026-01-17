<?php

declare(strict_types=1);

namespace LaraForge\Session;

class Session
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $branch,
        public readonly ?string $worktree,
        public readonly ?string $workflowType,
        public readonly ?string $workflowName,
        public readonly string $startedAt,
        public readonly string $lastActivity,
        public readonly ?int $pid,
    ) {}

    /**
     * Get a human-readable description.
     */
    public function description(): string
    {
        $parts = [];

        if ($this->workflowType) {
            $parts[] = ucfirst($this->workflowType);
        }

        if ($this->workflowName) {
            $parts[] = "\"{$this->workflowName}\"";
        }

        if ($this->branch) {
            $parts[] = "on {$this->branch}";
        }

        if ($this->worktree) {
            $parts[] = '(worktree)';
        }

        return implode(' ', $parts) ?: 'Unknown session';
    }

    /**
     * Check if session is likely still active.
     */
    public function isLikelyActive(): bool
    {
        $lastActivity = strtotime($this->lastActivity);
        $threshold = 300; // 5 minutes

        return (time() - $lastActivity) < $threshold;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'branch' => $this->branch,
            'worktree' => $this->worktree,
            'workflow_type' => $this->workflowType,
            'workflow_name' => $this->workflowName,
            'started_at' => $this->startedAt,
            'last_activity' => $this->lastActivity,
            'pid' => $this->pid,
        ];
    }
}
