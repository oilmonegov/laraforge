<?php

declare(strict_types=1);

namespace LaraForge\Session;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class SessionManager
{
    private Filesystem $filesystem;

    private string $sessionsPath;

    private string $currentSessionId;

    public function __construct(
        private readonly string $workingDirectory,
    ) {
        $this->filesystem = new Filesystem;
        $this->sessionsPath = $workingDirectory.'/.laraforge/sessions.yaml';
        $this->currentSessionId = $this->generateSessionId();
    }

    /**
     * Check if git is initialized.
     */
    public function isGitInitialized(): bool
    {
        return $this->filesystem->exists($this->workingDirectory.'/.git');
    }

    /**
     * Initialize git repository.
     */
    public function initGit(): bool
    {
        if ($this->isGitInitialized()) {
            return true;
        }

        $result = [];
        $exitCode = 0;
        exec('cd '.escapeshellarg($this->workingDirectory).' && git init 2>&1', $result, $exitCode);

        return $exitCode === 0;
    }

    /**
     * Get current branch name.
     */
    public function currentBranch(): ?string
    {
        if (! $this->isGitInitialized()) {
            return null;
        }

        $result = [];
        exec('cd '.escapeshellarg($this->workingDirectory).' && git branch --show-current 2>/dev/null', $result);

        return $result[0] ?? null;
    }

    /**
     * Check if there are other active sessions.
     *
     * @return array<Session>
     */
    public function getActiveSessions(): array
    {
        $sessions = $this->loadSessions();
        $active = [];
        $staleThreshold = 300; // 5 minutes

        foreach ($sessions as $id => $data) {
            $lastActivity = strtotime($data['last_activity'] ?? '');
            $isStale = (time() - $lastActivity) > $staleThreshold;

            if (! $isStale && $id !== $this->currentSessionId) {
                $active[] = new Session(
                    id: $id,
                    branch: $data['branch'] ?? null,
                    worktree: $data['worktree'] ?? null,
                    workflowType: $data['workflow_type'] ?? null,
                    workflowName: $data['workflow_name'] ?? null,
                    startedAt: $data['started_at'] ?? '',
                    lastActivity: $data['last_activity'] ?? '',
                    pid: $data['pid'] ?? null,
                );
            }
        }

        return $active;
    }

    /**
     * Check if we're in a worktree.
     */
    public function isWorktree(): bool
    {
        $gitDir = $this->workingDirectory.'/.git';

        if (! $this->filesystem->exists($gitDir)) {
            return false;
        }

        // If .git is a file (not directory), it's a worktree
        return is_file($gitDir);
    }

    /**
     * Get the main repository path if we're in a worktree.
     */
    public function mainRepositoryPath(): ?string
    {
        if (! $this->isWorktree()) {
            return null;
        }

        $gitFile = file_get_contents($this->workingDirectory.'/.git');
        if ($gitFile === false) {
            return null;
        }

        // Parse "gitdir: /path/to/.git/worktrees/name"
        if (preg_match('/gitdir:\s*(.+)/', $gitFile, $matches)) {
            $worktreeGitDir = trim($matches[1]);
            // Go up from .git/worktrees/name to get main repo
            $mainGitDir = dirname(dirname(dirname($worktreeGitDir)));

            return dirname($mainGitDir);
        }

        return null;
    }

    /**
     * Start a new session.
     */
    public function startSession(?string $workflowType = null, ?string $workflowName = null): Session
    {
        $session = new Session(
            id: $this->currentSessionId,
            branch: $this->currentBranch(),
            worktree: $this->isWorktree() ? $this->workingDirectory : null,
            workflowType: $workflowType,
            workflowName: $workflowName,
            startedAt: date('c'),
            lastActivity: date('c'),
            pid: getmypid() ?: null,
        );

        $this->saveSession($session);

        return $session;
    }

    /**
     * Update session activity.
     */
    public function heartbeat(): void
    {
        $sessions = $this->loadSessions();

        if (isset($sessions[$this->currentSessionId])) {
            $sessions[$this->currentSessionId]['last_activity'] = date('c');
            $sessions[$this->currentSessionId]['branch'] = $this->currentBranch();
            $this->saveSessions($sessions);
        }
    }

    /**
     * End the current session.
     */
    public function endSession(): void
    {
        $sessions = $this->loadSessions();
        unset($sessions[$this->currentSessionId]);
        $this->saveSessions($sessions);
    }

    /**
     * Clean up stale sessions.
     */
    public function cleanupStaleSessions(): int
    {
        $sessions = $this->loadSessions();
        $staleThreshold = 300; // 5 minutes
        $cleaned = 0;

        foreach ($sessions as $id => $data) {
            $lastActivity = strtotime($data['last_activity'] ?? '');
            $isStale = (time() - $lastActivity) > $staleThreshold;

            // Also check if process is still running
            $pid = $data['pid'] ?? null;
            $processRunning = $pid && $this->isProcessRunning($pid);

            if ($isStale && ! $processRunning) {
                unset($sessions[$id]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->saveSessions($sessions);
        }

        return $cleaned;
    }

    /**
     * Get current session ID.
     */
    public function currentSessionId(): string
    {
        return $this->currentSessionId;
    }

    /**
     * Create a new worktree for parallel work.
     */
    public function createWorktree(string $name, ?string $baseBranch = null): ?string
    {
        if (! $this->isGitInitialized()) {
            return null;
        }

        $worktreesDir = $this->workingDirectory.'/.laraforge/worktrees';
        if (! $this->filesystem->exists($worktreesDir)) {
            $this->filesystem->mkdir($worktreesDir, 0755);
        }

        $worktreePath = $worktreesDir.'/'.$name;
        $branchName = 'worktree/'.$name;

        // Create worktree with new branch
        $base = $baseBranch ?? $this->currentBranch() ?? 'main';
        $command = sprintf(
            'cd %s && git worktree add -b %s %s %s 2>&1',
            escapeshellarg($this->workingDirectory),
            escapeshellarg($branchName),
            escapeshellarg($worktreePath),
            escapeshellarg($base)
        );

        $result = [];
        $exitCode = 0;
        exec($command, $result, $exitCode);

        if ($exitCode === 0) {
            return $worktreePath;
        }

        return null;
    }

    /**
     * List existing worktrees.
     *
     * @return array<array{path: string, branch: string, head: string}>
     */
    public function listWorktrees(): array
    {
        if (! $this->isGitInitialized()) {
            return [];
        }

        $result = [];
        exec('cd '.escapeshellarg($this->workingDirectory).' && git worktree list --porcelain 2>/dev/null', $result);

        $worktrees = [];
        $current = [];

        foreach ($result as $line) {
            if (str_starts_with($line, 'worktree ')) {
                if (! empty($current)) {
                    $worktrees[] = $current;
                }
                $current = ['path' => substr($line, 9)];
            } elseif (str_starts_with($line, 'HEAD ')) {
                $current['head'] = substr($line, 5);
            } elseif (str_starts_with($line, 'branch ')) {
                $current['branch'] = substr($line, 7);
            }
        }

        if (! empty($current)) {
            $worktrees[] = $current;
        }

        return $worktrees;
    }

    /**
     * Detect conflict with another session.
     */
    public function detectConflict(): ?SessionConflict
    {
        $activeSessions = $this->getActiveSessions();

        if (empty($activeSessions)) {
            return null;
        }

        $currentBranch = $this->currentBranch();

        foreach ($activeSessions as $session) {
            // Same branch = conflict
            if ($session->branch === $currentBranch && ! $this->isWorktree() && ! $session->worktree) {
                return new SessionConflict(
                    type: 'same_branch',
                    message: "Another session is working on branch '{$currentBranch}'",
                    conflictingSession: $session,
                    suggestion: 'Create a worktree or switch to a different branch',
                );
            }
        }

        return null;
    }

    /**
     * Generate suggested worktree name.
     */
    public function suggestWorktreeName(?string $workflowType = null, ?string $workflowName = null): string
    {
        $base = $workflowType ?? 'session';
        $slug = $workflowName
            ? preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($workflowName))
            : date('Ymd-His');

        return $base.'-'.$slug;
    }

    private function generateSessionId(): string
    {
        return sprintf(
            '%s-%s-%d',
            gethostname() ?: 'unknown',
            date('YmdHis'),
            getmypid() ?: random_int(1000, 9999)
        );
    }

    private function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output);

            return count($output) > 1;
        }

        return posix_kill($pid, 0);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadSessions(): array
    {
        if (! $this->filesystem->exists($this->sessionsPath)) {
            return [];
        }

        $data = Yaml::parseFile($this->sessionsPath);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $sessions
     */
    private function saveSessions(array $sessions): void
    {
        $dir = dirname($this->sessionsPath);
        if (! $this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir, 0755);
        }

        $yaml = Yaml::dump($sessions, 4);
        $this->filesystem->dumpFile($this->sessionsPath, $yaml);
    }

    private function saveSession(Session $session): void
    {
        $sessions = $this->loadSessions();
        $sessions[$session->id] = $session->toArray();
        $this->saveSessions($sessions);
    }
}
