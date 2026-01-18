<?php

declare(strict_types=1);

namespace LaraForge\AgentSupport;

use LaraForge\AgentSupport\Agents\ClaudeCodeSupport;
use LaraForge\AgentSupport\Agents\CursorSupport;
use LaraForge\AgentSupport\Agents\JetBrainsSupport;
use LaraForge\AgentSupport\Agents\WindsurfSupport;

/**
 * Agent Support Factory
 *
 * Creates and configures the agent support registry with all available agents.
 */
final class AgentSupportFactory
{
    /**
     * Create a fully configured registry with all agent supports.
     */
    public static function create(): AgentSupportRegistry
    {
        $registry = new AgentSupportRegistry;

        // Register all agent supports
        $registry->register(new ClaudeCodeSupport);
        $registry->register(new CursorSupport);
        $registry->register(new JetBrainsSupport);
        $registry->register(new WindsurfSupport);

        return $registry;
    }

    /**
     * Get all available agent identifiers.
     *
     * @return array<string>
     */
    public static function availableAgents(): array
    {
        return [
            'claude-code',
            'cursor',
            'jetbrains',
            'windsurf',
        ];
    }

    /**
     * Get the primary/recommended agent.
     */
    public static function primaryAgent(): string
    {
        return 'claude-code';
    }

    /**
     * Get agents that are commonly used together.
     *
     * @return array<string, array<string>>
     */
    public static function agentCombinations(): array
    {
        return [
            'minimal' => ['claude-code'],
            'standard' => ['claude-code', 'cursor'],
            'comprehensive' => ['claude-code', 'cursor', 'jetbrains', 'windsurf'],
        ];
    }
}
