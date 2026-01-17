<?php

declare(strict_types=1);

namespace LaraForge\Skills\GitSkills;

use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillResult;
use LaraForge\Worktree\WorktreeManager;

class WorktreeSkill extends Skill
{
    public function identifier(): string
    {
        return 'create-worktree';
    }

    public function name(): string
    {
        return 'Create Worktree';
    }

    public function description(): string
    {
        return 'Creates a git worktree for parallel agent work';
    }

    public function parameters(): array
    {
        return [
            'feature_id' => [
                'type' => 'string',
                'description' => 'Feature identifier',
                'required' => true,
            ],
            'agent_id' => [
                'type' => 'string',
                'description' => 'Agent identifier',
                'required' => true,
            ],
            'base_branch' => [
                'type' => 'string',
                'description' => 'Base branch to create from',
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
        return ['git', 'worktree', 'parallel', 'multi-agent'];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        $featureId = $params['feature_id'];
        $agentId = $params['agent_id'];
        $baseBranch = $params['base_branch'] ?? null;

        $worktreesDir = $context->worktreesDir();
        $manager = new WorktreeManager($context->workingDirectory(), $worktreesDir);

        try {
            $session = $manager->createSession($featureId, $agentId, $baseBranch);

            return SkillResult::success(
                output: $session->path(),
                artifacts: [
                    'session_id' => $session->id(),
                    'path' => $session->path(),
                    'branch' => $session->branch(),
                ],
                metadata: [
                    'feature_id' => $featureId,
                    'agent_id' => $agentId,
                ]
            );
        } catch (\Throwable $e) {
            return SkillResult::failure(
                "Failed to create worktree: {$e->getMessage()}",
                metadata: ['exception' => $e->getMessage()]
            );
        }
    }
}
