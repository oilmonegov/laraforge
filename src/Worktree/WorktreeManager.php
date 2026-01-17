<?php

declare(strict_types=1);

namespace LaraForge\Worktree;

use LaraForge\Worktree\Contracts\MergeResultInterface;
use LaraForge\Worktree\Contracts\SessionInterface;
use LaraForge\Worktree\Contracts\WorktreeManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class WorktreeManager implements WorktreeManagerInterface
{
    /**
     * @var array<string, AgentSession>
     */
    private array $sessions = [];

    private Filesystem $filesystem;

    private string $sessionsFile;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $worktreesDir,
    ) {
        $this->filesystem = new Filesystem;
        $this->sessionsFile = $this->worktreesDir.'/sessions.yaml';
        $this->ensureDirectoryExists();
        $this->loadSessions();
    }

    public function createSession(string $featureId, string $agentId, ?string $baseBranch = null): SessionInterface
    {
        $baseBranch = $baseBranch ?? $this->getDefaultBranch();
        $branchName = $this->generateBranchName($featureId, $agentId);
        $worktreePath = $this->worktreesDir.'/'.$this->slugify($featureId.'-'.$agentId);

        // Create the branch if it doesn't exist
        $this->createBranchIfNotExists($branchName, $baseBranch);

        // Create the worktree
        $this->runGit(['worktree', 'add', $worktreePath, $branchName]);

        $session = AgentSession::create(
            path: $worktreePath,
            branch: $branchName,
            featureId: $featureId,
            agentId: $agentId,
        );

        $this->sessions[$session->id()] = $session;
        $this->saveSessions();

        return $session;
    }

    public function getSession(string $sessionId): ?SessionInterface
    {
        return $this->sessions[$sessionId] ?? null;
    }

    public function activeSessions(): array
    {
        return array_values(array_filter(
            $this->sessions,
            fn (AgentSession $s) => $s->isActive()
        ));
    }

    public function sessionsForFeature(string $featureId): array
    {
        return array_values(array_filter(
            $this->sessions,
            fn (AgentSession $s) => $s->featureId() === $featureId
        ));
    }

    public function sessionsForAgent(string $agentId): array
    {
        return array_values(array_filter(
            $this->sessions,
            fn (AgentSession $s) => $s->agentId() === $agentId
        ));
    }

    public function pauseSession(string $sessionId): void
    {
        $session = $this->sessions[$sessionId] ?? null;
        if ($session) {
            $session->setStatus('paused');
            $this->saveSessions();
        }
    }

    public function resumeSession(string $sessionId): SessionInterface
    {
        $session = $this->sessions[$sessionId] ?? null;
        if (! $session) {
            throw new \RuntimeException("Session not found: {$sessionId}");
        }

        $session->setStatus('active');
        $this->saveSessions();

        return $session;
    }

    public function completeSession(string $sessionId): void
    {
        $session = $this->sessions[$sessionId] ?? null;
        if ($session) {
            $session->setStatus('completed');
            $this->saveSessions();
        }
    }

    public function abandonSession(string $sessionId): void
    {
        $session = $this->sessions[$sessionId] ?? null;
        if (! $session) {
            return;
        }

        // Remove the worktree
        if ($this->filesystem->exists($session->path())) {
            $this->runGit(['worktree', 'remove', '--force', $session->path()]);
        }

        // Delete the branch
        $this->runGit(['branch', '-D', $session->branch()], false);

        $session->setStatus('abandoned');
        $this->saveSessions();
    }

    public function mergeSession(string $sessionId, ?string $targetBranch = null): MergeResultInterface
    {
        $session = $this->sessions[$sessionId] ?? null;
        if (! $session) {
            return MergeResult::failure(
                $targetBranch ?? 'unknown',
                [],
                "Session not found: {$sessionId}"
            );
        }

        return $this->mergeSessions([$sessionId], $targetBranch);
    }

    public function mergeSessions(array $sessionIds, ?string $targetBranch = null): MergeResultInterface
    {
        $targetBranch = $targetBranch ?? $this->getDefaultBranch();
        $sourceBranches = [];

        foreach ($sessionIds as $sessionId) {
            $session = $this->sessions[$sessionId] ?? null;
            if ($session) {
                $sourceBranches[] = $session->branch();
            }
        }

        if (empty($sourceBranches)) {
            return MergeResult::failure($targetBranch, [], 'No valid sessions to merge');
        }

        // Check for conflicts first
        $conflicts = $this->detectConflicts($sessionIds);
        if (! empty($conflicts)) {
            return MergeResult::conflict($targetBranch, $sourceBranches, $conflicts);
        }

        // Switch to target branch
        $this->runGit(['checkout', $targetBranch]);

        $mergedFiles = [];
        foreach ($sourceBranches as $branch) {
            $result = $this->runGit(['merge', '--no-ff', $branch, '-m', "Merge {$branch} into {$targetBranch}"], false);

            if ($result['code'] !== 0) {
                // Abort the merge
                $this->runGit(['merge', '--abort'], false);

                return MergeResult::failure(
                    $targetBranch,
                    $sourceBranches,
                    "Failed to merge {$branch}: {$result['stderr']}"
                );
            }

            $mergedFiles = array_merge($mergedFiles, $this->getChangedFiles($branch, $targetBranch));
        }

        // Get the merge commit hash
        $hashResult = $this->runGit(['rev-parse', 'HEAD']);
        $commitHash = trim($hashResult['stdout']);

        // Mark sessions as merged
        foreach ($sessionIds as $sessionId) {
            $session = $this->sessions[$sessionId] ?? null;
            if ($session) {
                $session->setStatus('merged');
            }
        }
        $this->saveSessions();

        return MergeResult::success(
            $targetBranch,
            $sourceBranches,
            $commitHash,
            array_unique($mergedFiles)
        );
    }

    public function detectConflicts(array $sessionIds): array
    {
        $conflicts = [];
        $modifiedFiles = [];

        // Collect all modified files from all sessions
        foreach ($sessionIds as $sessionId) {
            $session = $this->sessions[$sessionId] ?? null;
            if (! $session) {
                continue;
            }

            $files = $this->getModifiedFilesInBranch($session->branch());
            foreach ($files as $file) {
                if (! isset($modifiedFiles[$file])) {
                    $modifiedFiles[$file] = [];
                }
                $modifiedFiles[$file][] = [
                    'session_id' => $sessionId,
                    'branch' => $session->branch(),
                ];
            }
        }

        // Check for files modified in multiple sessions
        foreach ($modifiedFiles as $file => $sessions) {
            if (count($sessions) > 1) {
                $conflicts[] = Conflict::content(
                    $file,
                    array_column($sessions, 'branch'),
                    [], // Would need to extract actual content sections
                    array_column($sessions, 'session_id')
                );
            }
        }

        return $conflicts;
    }

    public function cleanup(int $olderThanDays = 7): int
    {
        $threshold = new \DateTimeImmutable("-{$olderThanDays} days");
        $cleaned = 0;

        foreach ($this->sessions as $sessionId => $session) {
            if (! $session->isActive() && $session->lastActivityAt() < $threshold) {
                // Remove worktree if it exists
                if ($this->filesystem->exists($session->path())) {
                    $this->runGit(['worktree', 'remove', '--force', $session->path()], false);
                }

                // Remove from tracking
                unset($this->sessions[$sessionId]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->saveSessions();
            $this->runGit(['worktree', 'prune'], false);
        }

        return $cleaned;
    }

    public function listWorktrees(): array
    {
        $result = $this->runGit(['worktree', 'list', '--porcelain']);
        $worktrees = [];
        $current = [];

        foreach (explode("\n", $result['stdout']) as $line) {
            $line = trim($line);
            if (empty($line)) {
                if (! empty($current)) {
                    $worktrees[] = $current;
                    $current = [];
                }

                continue;
            }

            if (str_starts_with($line, 'worktree ')) {
                $current['path'] = substr($line, 9);
            } elseif (str_starts_with($line, 'HEAD ')) {
                $current['head'] = substr($line, 5);
            } elseif (str_starts_with($line, 'branch ')) {
                $current['branch'] = str_replace('refs/heads/', '', substr($line, 7));
            }
        }

        if (! empty($current)) {
            $worktrees[] = $current;
        }

        return $worktrees;
    }

    private function ensureDirectoryExists(): void
    {
        if (! $this->filesystem->exists($this->worktreesDir)) {
            $this->filesystem->mkdir($this->worktreesDir, 0755);
        }
    }

    private function loadSessions(): void
    {
        if (! $this->filesystem->exists($this->sessionsFile)) {
            return;
        }

        $data = Yaml::parseFile($this->sessionsFile);
        if (! is_array($data) || ! isset($data['sessions'])) {
            return;
        }

        foreach ($data['sessions'] as $sessionData) {
            $session = AgentSession::fromArray($sessionData);
            $this->sessions[$session->id()] = $session;
        }
    }

    private function saveSessions(): void
    {
        $data = [
            'sessions' => array_map(
                fn (AgentSession $s) => $s->toArray(),
                $this->sessions
            ),
        ];

        $this->filesystem->dumpFile(
            $this->sessionsFile,
            Yaml::dump($data, 10, 2)
        );
    }

    private function getDefaultBranch(): string
    {
        // Try to detect the default branch
        $result = $this->runGit(['symbolic-ref', '--short', 'refs/remotes/origin/HEAD'], false);
        if ($result['code'] === 0) {
            return str_replace('origin/', '', trim($result['stdout']));
        }

        // Fall back to common defaults
        foreach (['main', 'master'] as $branch) {
            $result = $this->runGit(['rev-parse', '--verify', $branch], false);
            if ($result['code'] === 0) {
                return $branch;
            }
        }

        return 'main';
    }

    private function generateBranchName(string $featureId, string $agentId): string
    {
        $slug = $this->slugify($featureId);

        return "feature/{$slug}-{$agentId}";
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text) ?? '';
        $text = preg_replace('/\s+/', '-', trim($text)) ?? '';

        return strtolower($text);
    }

    private function createBranchIfNotExists(string $branch, string $baseBranch): void
    {
        $result = $this->runGit(['rev-parse', '--verify', $branch], false);
        if ($result['code'] !== 0) {
            $this->runGit(['branch', $branch, $baseBranch]);
        }
    }

    private function getModifiedFilesInBranch(string $branch): array
    {
        $baseBranch = $this->getDefaultBranch();
        $result = $this->runGit(['diff', '--name-only', "{$baseBranch}...{$branch}"], false);

        if ($result['code'] !== 0) {
            return [];
        }

        return array_filter(explode("\n", trim($result['stdout'])));
    }

    private function getChangedFiles(string $fromBranch, string $toBranch): array
    {
        $result = $this->runGit(['diff', '--name-only', "{$toBranch}...{$fromBranch}"], false);

        if ($result['code'] !== 0) {
            return [];
        }

        return array_filter(explode("\n", trim($result['stdout'])));
    }

    /**
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runGit(array $args, bool $throwOnError = true): array
    {
        $process = new Process(['git', ...$args], $this->projectRoot);
        $process->run();

        $result = [
            'code' => $process->getExitCode(),
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];

        if ($throwOnError && $result['code'] !== 0) {
            throw new \RuntimeException(
                'Git command failed: git '.implode(' ', $args)."\n".$result['stderr']
            );
        }

        return $result;
    }
}
