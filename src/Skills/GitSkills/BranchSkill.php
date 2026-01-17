<?php

declare(strict_types=1);

namespace LaraForge\Skills\GitSkills;

use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillResult;
use Symfony\Component\Process\Process;

class BranchSkill extends Skill
{
    public function identifier(): string
    {
        return 'create-branch';
    }

    public function name(): string
    {
        return 'Create Branch';
    }

    public function description(): string
    {
        return 'Creates a new git branch for a feature or bugfix';
    }

    public function parameters(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'Branch name (without prefix)',
                'required' => true,
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Branch type: feature, bugfix, hotfix, release',
                'required' => false,
                'default' => 'feature',
            ],
            'base_branch' => [
                'type' => 'string',
                'description' => 'Base branch to create from',
                'required' => false,
            ],
            'checkout' => [
                'type' => 'boolean',
                'description' => 'Checkout the branch after creation',
                'required' => false,
                'default' => true,
            ],
            'feature_id' => [
                'type' => 'string',
                'description' => 'Associated feature identifier',
                'required' => false,
            ],
        ];
    }

    public function category(): string
    {
        return 'git';
    }

    public function tags(): array
    {
        return ['git', 'branch', 'version-control'];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        $name = $this->slugify($params['name']);
        $type = $params['type'] ?? 'feature';
        $baseBranch = $params['base_branch'] ?? $this->getDefaultBranch($context);
        $checkout = $params['checkout'] ?? true;

        $branchName = "{$type}/{$name}";

        // Check if branch already exists
        if ($this->branchExists($branchName, $context)) {
            if ($checkout) {
                $this->checkout($branchName, $context);
            }

            return SkillResult::success(
                output: $branchName,
                artifacts: ['branch_name' => $branchName],
                metadata: ['already_existed' => true]
            );
        }

        // Create the branch
        $result = $this->runGit(['branch', $branchName, $baseBranch], $context);
        if ($result['code'] !== 0) {
            return SkillResult::failure(
                "Failed to create branch: {$result['stderr']}",
                metadata: ['git_error' => $result['stderr']]
            );
        }

        // Checkout if requested
        if ($checkout) {
            $checkoutResult = $this->checkout($branchName, $context);
            if (! $checkoutResult['success']) {
                return SkillResult::failure(
                    "Branch created but checkout failed: {$checkoutResult['error']}",
                    output: $branchName,
                    metadata: ['checkout_error' => $checkoutResult['error']]
                );
            }
        }

        // Update feature if exists
        $feature = $context->currentFeature();
        if ($feature) {
            $feature->setBranch($branchName);
        }

        return SkillResult::success(
            output: $branchName,
            artifacts: ['branch_name' => $branchName, 'base_branch' => $baseBranch],
            nextSteps: [
                [
                    'skill' => 'implement',
                    'params' => ['feature_id' => $params['feature_id'] ?? null],
                    'reason' => 'Start implementation on the new branch',
                ],
            ],
            metadata: [
                'checked_out' => $checkout,
                'type' => $type,
            ]
        );
    }

    private function branchExists(string $branch, ProjectContext $context): bool
    {
        $result = $this->runGit(['rev-parse', '--verify', $branch], $context);

        return $result['code'] === 0;
    }

    private function checkout(string $branch, ProjectContext $context): array
    {
        $result = $this->runGit(['checkout', $branch], $context);

        return [
            'success' => $result['code'] === 0,
            'error' => $result['stderr'],
        ];
    }

    private function getDefaultBranch(ProjectContext $context): string
    {
        $result = $this->runGit(['symbolic-ref', '--short', 'refs/remotes/origin/HEAD'], $context);
        if ($result['code'] === 0) {
            return str_replace('origin/', '', trim($result['stdout']));
        }

        // Fall back to common defaults
        foreach (['main', 'master'] as $branch) {
            if ($this->branchExists($branch, $context)) {
                return $branch;
            }
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

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text) ?? '';
        $text = preg_replace('/\s+/', '-', trim($text)) ?? '';

        return strtolower($text);
    }
}
