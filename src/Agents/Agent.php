<?php

declare(strict_types=1);

namespace LaraForge\Agents;

use LaraForge\Agents\Contracts\AgentInterface;
use LaraForge\Agents\Contracts\AgentResultInterface;
use LaraForge\Agents\Contracts\TaskInterface;
use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillInterface;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\SkillRegistry;

abstract class Agent implements AgentInterface
{
    protected ?LaraForgeInterface $laraforge = null;

    protected ?SkillRegistry $skills = null;

    /**
     * @var array<SkillResultInterface>
     */
    protected array $skillResults = [];

    public function setLaraForge(LaraForgeInterface $laraforge): void
    {
        $this->laraforge = $laraforge;
    }

    public function setSkillRegistry(SkillRegistry $skills): void
    {
        $this->skills = $skills;
    }

    abstract public function identifier(): string;

    abstract public function name(): string;

    abstract public function role(): string;

    abstract public function description(): string;

    abstract public function capabilities(): array;

    abstract protected function perform(TaskInterface $task, ProjectContext $context): AgentResultInterface;

    public function execute(TaskInterface $task, ProjectContext $context): AgentResultInterface
    {
        $this->skillResults = [];

        if (! $this->canHandle($task)) {
            return AgentResult::failure(
                $task,
                "Agent '{$this->name()}' cannot handle task of type '{$task->type()}'"
            );
        }

        if ($task instanceof Task) {
            $task->setStatus('in_progress');
            $task->setAssignee($this->identifier());
        }

        $result = $this->perform($task, $context);

        if ($task instanceof Task) {
            $task->setStatus($result->isSuccess() ? 'completed' : 'failed');
        }

        return $result;
    }

    public function handoff(AgentInterface $to, ProjectContext $context, array $data = []): void
    {
        // Log the handoff
        if ($this->laraforge) {
            // Could emit an event or log here
        }
    }

    public function canHandle(TaskInterface $task): bool
    {
        // By default, agents can handle tasks based on their role
        $roleTaskMapping = [
            'analyst' => ['feature', 'requirements', 'research'],
            'architect' => ['design', 'architecture', 'feature'],
            'developer' => ['feature', 'bugfix', 'refactor', 'implement'],
            'tester' => ['test', 'qa', 'validation'],
            'reviewer' => ['review', 'code-review'],
            'pm' => ['planning', 'tracking', 'feature'],
        ];

        $handledTypes = $roleTaskMapping[$this->role()] ?? [];

        return in_array($task->type(), $handledTypes, true);
    }

    public function priority(TaskInterface $task): int
    {
        // Higher priority if task type matches role directly
        $roleTaskMapping = [
            'analyst' => 'requirements',
            'architect' => 'design',
            'developer' => 'implement',
            'tester' => 'test',
            'reviewer' => 'review',
            'pm' => 'planning',
        ];

        $primaryType = $roleTaskMapping[$this->role()] ?? '';

        if ($task->type() === $primaryType) {
            return 100;
        }

        if ($this->canHandle($task)) {
            return 50;
        }

        return 0;
    }

    protected function executeSkill(string $identifier, array $params): ?SkillResultInterface
    {
        if (! $this->skills) {
            return null;
        }

        $skill = $this->skills->get($identifier);
        if (! $skill) {
            return null;
        }

        if (! in_array($identifier, $this->capabilities(), true)) {
            return null;
        }

        $result = $skill->execute($params);
        $this->skillResults[] = $result;

        return $result;
    }

    protected function getSkill(string $identifier): ?SkillInterface
    {
        return $this->skills?->get($identifier);
    }

    protected function hasSkill(string $identifier): bool
    {
        return $this->skills?->has($identifier) ?? false;
    }

    protected function createContext(): ProjectContext
    {
        if (! $this->laraforge) {
            throw new \RuntimeException('LaraForge instance not set on agent');
        }

        return new ProjectContext($this->laraforge);
    }

    protected function success(
        TaskInterface $task,
        mixed $output = null,
        array $artifacts = [],
        array $nextTasks = [],
        array $metadata = [],
    ): AgentResult {
        return AgentResult::success(
            task: $task,
            output: $output,
            artifacts: $artifacts,
            skillResults: $this->skillResults,
            nextTasks: $nextTasks,
            metadata: $metadata,
        );
    }

    protected function failure(
        TaskInterface $task,
        string $error,
        mixed $output = null,
        array $artifacts = [],
        array $metadata = [],
    ): AgentResult {
        return AgentResult::failure(
            task: $task,
            error: $error,
            output: $output,
            artifacts: $artifacts,
            skillResults: $this->skillResults,
            metadata: $metadata,
        );
    }

    protected function handoffTo(
        TaskInterface $task,
        string $toAgent,
        string $reason,
        array $data = [],
        array $metadata = [],
    ): AgentResult {
        return AgentResult::withHandoff(
            task: $task,
            toAgent: $toAgent,
            reason: $reason,
            data: $data,
            metadata: $metadata,
        );
    }
}
