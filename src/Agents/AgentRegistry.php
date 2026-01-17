<?php

declare(strict_types=1);

namespace LaraForge\Agents;

use LaraForge\Agents\Contracts\AgentInterface;
use LaraForge\Agents\Contracts\TaskInterface;
use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Skills\SkillRegistry;

class AgentRegistry
{
    /**
     * @var array<string, AgentInterface>
     */
    private array $agents = [];

    public function __construct(
        private readonly ?LaraForgeInterface $laraforge = null,
        private readonly ?SkillRegistry $skills = null,
    ) {}

    public function register(AgentInterface $agent): void
    {
        if ($agent instanceof Agent) {
            if ($this->laraforge) {
                $agent->setLaraForge($this->laraforge);
            }
            if ($this->skills) {
                $agent->setSkillRegistry($this->skills);
            }
        }

        $this->agents[$agent->identifier()] = $agent;
    }

    public function get(string $identifier): ?AgentInterface
    {
        return $this->agents[$identifier] ?? null;
    }

    public function has(string $identifier): bool
    {
        return isset($this->agents[$identifier]);
    }

    /**
     * @return array<string, AgentInterface>
     */
    public function all(): array
    {
        return $this->agents;
    }

    /**
     * Get agents by role.
     *
     * @return array<string, AgentInterface>
     */
    public function byRole(string $role): array
    {
        return array_filter(
            $this->agents,
            fn (AgentInterface $agent) => $agent->role() === $role
        );
    }

    /**
     * Find the best agent for a given task.
     */
    public function findForTask(TaskInterface $task): ?AgentInterface
    {
        $bestAgent = null;
        $bestPriority = -1;

        foreach ($this->agents as $agent) {
            if ($agent->canHandle($task)) {
                $priority = $agent->priority($task);
                if ($priority > $bestPriority) {
                    $bestPriority = $priority;
                    $bestAgent = $agent;
                }
            }
        }

        return $bestAgent;
    }

    /**
     * Find all agents that can handle a task.
     *
     * @return array<AgentInterface>
     */
    public function findAllForTask(TaskInterface $task): array
    {
        $agents = [];

        foreach ($this->agents as $agent) {
            if ($agent->canHandle($task)) {
                $agents[] = [
                    'agent' => $agent,
                    'priority' => $agent->priority($task),
                ];
            }
        }

        // Sort by priority descending
        usort($agents, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return array_map(fn ($item) => $item['agent'], $agents);
    }

    /**
     * Get agents that have a specific capability (skill).
     *
     * @return array<string, AgentInterface>
     */
    public function withCapability(string $skill): array
    {
        return array_filter(
            $this->agents,
            fn (AgentInterface $agent) => in_array($skill, $agent->capabilities(), true)
        );
    }

    /**
     * Get all available roles.
     *
     * @return array<string>
     */
    public function roles(): array
    {
        $roles = [];
        foreach ($this->agents as $agent) {
            $roles[$agent->role()] = true;
        }

        return array_keys($roles);
    }

    /**
     * Get agent metadata for all registered agents.
     *
     * @return array<string, array{identifier: string, name: string, role: string, description: string, capabilities: array}>
     */
    public function metadata(): array
    {
        $metadata = [];
        foreach ($this->agents as $identifier => $agent) {
            $metadata[$identifier] = [
                'identifier' => $agent->identifier(),
                'name' => $agent->name(),
                'role' => $agent->role(),
                'description' => $agent->description(),
                'capabilities' => $agent->capabilities(),
            ];
        }

        return $metadata;
    }

    public function remove(string $identifier): void
    {
        unset($this->agents[$identifier]);
    }

    public function count(): int
    {
        return count($this->agents);
    }
}
