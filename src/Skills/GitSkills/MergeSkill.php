<?php

declare(strict_types=1);

namespace LaraForge\Skills\GitSkills;

use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillResult;
use Symfony\Component\Process\Process;

class MergeSkill extends Skill
{
    public function identifier(): string
    {
        return 'merge-branch';
    }

    public function name(): string
    {
        return 'Merge Branch';
    }

    public function description(): string
    {
        return 'Merges a branch into another branch';
    }

    public function parameters(): array
    {
        return [
            'source_branch' => [
                'type' => 'string',
                'description' => 'Branch to merge from',
                'required' => true,
            ],
            'target_branch' => [
                'type' => 'string',
                'description' => 'Branch to merge into',
                'required' => false,
            ],
            'strategy' => [
                'type' => 'string',
                'description' => 'Merge strategy: merge, squash, rebase',
                'required' => false,
                'default' => 'merge',
            ],
            'message' => [
                'type' => 'string',
                'description' => 'Custom merge commit message',
                'required' => false,
            ],
            'no_ff' => [
                'type' => 'boolean',
                'description' => 'Create merge commit even for fast-forward',
                'required' => false,
                'default' => true,
            ],
            'delete_source' => [
                'type' => 'boolean',
                'description' => 'Delete source branch after merge',
                'required' => false,
                'default' => false,
            ],
        ];
    }

    public function category(): string
    {
        return 'git';
    }

    public function tags(): array
    {
        return ['git', 'merge', 'version-control'];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        $sourceBranch = $params['source_branch'];
        $targetBranch = $params['target_branch'] ?? $this->getDefaultBranch($context);
        $strategy = $params['strategy'] ?? 'merge';
        $message = $params['message'] ?? null;
        $noFf = $params['no_ff'] ?? true;
        $deleteSource = $params['delete_source'] ?? false;

        // Get current branch to restore later if needed
        $currentBranchResult = $this->runGit(['rev-parse', '--abbrev-ref', 'HEAD'], $context);
        $originalBranch = trim($currentBranchResult['stdout']);

        // Checkout target branch
        $checkoutResult = $this->runGit(['checkout', $targetBranch], $context);
        if ($checkoutResult['code'] !== 0) {
            return SkillResult::failure(
                "Failed to checkout target branch: {$checkoutResult['stderr']}",
                metadata: ['git_error' => $checkoutResult['stderr']]
            );
        }

        // Perform merge based on strategy
        $mergeResult = match ($strategy) {
            'squash' => $this->squashMerge($sourceBranch, $message, $context),
            'rebase' => $this->rebaseMerge($sourceBranch, $context),
            default => $this->normalMerge($sourceBranch, $message, $noFf, $context),
        };

        if (! $mergeResult['success']) {
            // Abort and restore
            $this->runGit(['merge', '--abort'], $context);
            $this->runGit(['checkout', $originalBranch], $context);

            return SkillResult::failure(
                "Merge failed: {$mergeResult['error']}",
                metadata: [
                    'git_error' => $mergeResult['error'],
                    'has_conflicts' => $mergeResult['conflicts'] ?? false,
                ]
            );
        }

        // Get merge commit hash
        $hashResult = $this->runGit(['rev-parse', 'HEAD'], $context);
        $commitHash = trim($hashResult['stdout']);

        // Delete source branch if requested
        if ($deleteSource) {
            $this->runGit(['branch', '-d', $sourceBranch], $context);
        }

        return SkillResult::success(
            output: $commitHash,
            artifacts: [
                'commit_hash' => $commitHash,
                'source_branch' => $sourceBranch,
                'target_branch' => $targetBranch,
            ],
            metadata: [
                'strategy' => $strategy,
                'source_deleted' => $deleteSource,
            ]
        );
    }

    private function normalMerge(string $branch, ?string $message, bool $noFf, ProjectContext $context): array
    {
        $args = ['merge'];

        if ($noFf) {
            $args[] = '--no-ff';
        }

        if ($message) {
            $args[] = '-m';
            $args[] = $message;
        }

        $args[] = $branch;

        $result = $this->runGit($args, $context);

        return [
            'success' => $result['code'] === 0,
            'error' => $result['stderr'],
            'conflicts' => str_contains($result['stdout'], 'CONFLICT'),
        ];
    }

    private function squashMerge(string $branch, ?string $message, ProjectContext $context): array
    {
        $mergeResult = $this->runGit(['merge', '--squash', $branch], $context);

        if ($mergeResult['code'] !== 0) {
            return [
                'success' => false,
                'error' => $mergeResult['stderr'],
                'conflicts' => str_contains($mergeResult['stdout'], 'CONFLICT'),
            ];
        }

        $commitMessage = $message ?? "Squash merge {$branch}";
        $commitResult = $this->runGit(['commit', '-m', $commitMessage], $context);

        return [
            'success' => $commitResult['code'] === 0,
            'error' => $commitResult['stderr'],
        ];
    }

    private function rebaseMerge(string $branch, ProjectContext $context): array
    {
        $result = $this->runGit(['rebase', $branch], $context);

        return [
            'success' => $result['code'] === 0,
            'error' => $result['stderr'],
            'conflicts' => str_contains($result['stdout'], 'CONFLICT'),
        ];
    }

    private function getDefaultBranch(ProjectContext $context): string
    {
        $result = $this->runGit(['symbolic-ref', '--short', 'refs/remotes/origin/HEAD'], $context);
        if ($result['code'] === 0) {
            return str_replace('origin/', '', trim($result['stdout']));
        }

        return 'main';
    }

    /**
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runGit(array $args, ProjectContext $context): array
    {
        $process = new Process(['git', ...$args], $context->workingDirectory());
        $process->run();

        return [
            'code' => $process->getExitCode(),
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }
}
