<?php

declare(strict_types=1);

namespace LaraForge\Skills\GitSkills;

use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillResult;
use Symfony\Component\Process\Process;

class CommitSkill extends Skill
{
    public function identifier(): string
    {
        return 'commit-changes';
    }

    public function name(): string
    {
        return 'Commit Changes';
    }

    public function description(): string
    {
        return 'Commits changes with conventional commit format';
    }

    public function parameters(): array
    {
        return [
            'type' => [
                'type' => 'string',
                'description' => 'Commit type: feat, fix, docs, style, refactor, test, chore',
                'required' => true,
            ],
            'scope' => [
                'type' => 'string',
                'description' => 'Commit scope (optional)',
                'required' => false,
            ],
            'message' => [
                'type' => 'string',
                'description' => 'Commit message',
                'required' => true,
            ],
            'body' => [
                'type' => 'string',
                'description' => 'Commit body for detailed description',
                'required' => false,
            ],
            'breaking' => [
                'type' => 'boolean',
                'description' => 'Is this a breaking change?',
                'required' => false,
                'default' => false,
            ],
            'files' => [
                'type' => 'array',
                'description' => 'Specific files to commit (empty for all staged)',
                'required' => false,
                'default' => [],
            ],
            'stage_all' => [
                'type' => 'boolean',
                'description' => 'Stage all changes before commit',
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
        return ['git', 'commit', 'version-control', 'conventional-commits'];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        $type = $params['type'];
        $scope = $params['scope'] ?? null;
        $message = $params['message'];
        $body = $params['body'] ?? null;
        $breaking = $params['breaking'] ?? false;
        $files = $params['files'] ?? [];
        $stageAll = $params['stage_all'] ?? false;

        // Stage files if needed
        if ($stageAll) {
            $stageResult = $this->runGit(['add', '-A'], $context);
            if ($stageResult['code'] !== 0) {
                return SkillResult::failure(
                    "Failed to stage files: {$stageResult['stderr']}",
                    metadata: ['git_error' => $stageResult['stderr']]
                );
            }
        } elseif (! empty($files)) {
            foreach ($files as $file) {
                $this->runGit(['add', $file], $context);
            }
        }

        // Check if there are changes to commit
        $statusResult = $this->runGit(['status', '--porcelain'], $context);
        if ($statusResult['code'] === 0 && empty(trim($statusResult['stdout']))) {
            return SkillResult::failure(
                'No changes to commit',
                metadata: ['status' => 'clean']
            );
        }

        // Build commit message
        $commitMessage = $this->buildCommitMessage($type, $scope, $message, $body, $breaking);

        // Create the commit
        $commitArgs = ['commit', '-m', $commitMessage];
        $commitResult = $this->runGit($commitArgs, $context);

        if ($commitResult['code'] !== 0) {
            return SkillResult::failure(
                "Failed to commit: {$commitResult['stderr']}",
                metadata: ['git_error' => $commitResult['stderr']]
            );
        }

        // Get the commit hash
        $hashResult = $this->runGit(['rev-parse', 'HEAD'], $context);
        $commitHash = trim($hashResult['stdout']);

        return SkillResult::success(
            output: $commitHash,
            artifacts: [
                'commit_hash' => $commitHash,
                'message' => $commitMessage,
            ],
            metadata: [
                'type' => $type,
                'scope' => $scope,
                'breaking' => $breaking,
            ]
        );
    }

    private function buildCommitMessage(
        string $type,
        ?string $scope,
        string $message,
        ?string $body,
        bool $breaking,
    ): string {
        $header = $type;

        if ($scope) {
            $header .= "({$scope})";
        }

        if ($breaking) {
            $header .= '!';
        }

        $header .= ': '.$message;

        $fullMessage = $header;

        if ($body) {
            $fullMessage .= "\n\n".$body;
        }

        return $fullMessage;
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
