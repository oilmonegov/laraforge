<?php

declare(strict_types=1);

namespace LaraForge\AgentSupport;

use LaraForge\AgentSupport\Contracts\AgentSupportInterface;

/**
 * Agent Support Registry
 *
 * Manages registration and retrieval of agent support implementations.
 * Allows discovering available agents and their capabilities.
 */
final class AgentSupportRegistry
{
    /**
     * @var array<string, AgentSupportInterface>
     */
    private array $supports = [];

    /**
     * Register an agent support.
     */
    public function register(AgentSupportInterface $support): self
    {
        $this->supports[$support->identifier()] = $support;

        return $this;
    }

    /**
     * Get an agent support by identifier.
     */
    public function get(string $identifier): ?AgentSupportInterface
    {
        return $this->supports[$identifier] ?? null;
    }

    /**
     * Check if an agent support is registered.
     */
    public function has(string $identifier): bool
    {
        return isset($this->supports[$identifier]);
    }

    /**
     * Get all registered agent supports.
     *
     * @return array<string, AgentSupportInterface>
     */
    public function all(): array
    {
        return $this->supports;
    }

    /**
     * Get all agent supports sorted by priority.
     *
     * @return array<AgentSupportInterface>
     */
    public function allByPriority(): array
    {
        $supports = array_values($this->supports);
        usort($supports, fn ($a, $b) => $b->priority() <=> $a->priority());

        return $supports;
    }

    /**
     * Get agent supports that are installed in the project.
     *
     * @return array<AgentSupportInterface>
     */
    public function installed(string $projectPath): array
    {
        return array_filter(
            $this->supports,
            fn (AgentSupportInterface $support) => $support->isInstalled($projectPath)
        );
    }

    /**
     * Get agent supports that are not yet installed.
     *
     * @return array<AgentSupportInterface>
     */
    public function available(string $projectPath): array
    {
        return array_filter(
            $this->supports,
            fn (AgentSupportInterface $support) => ! $support->isInstalled($projectPath)
        );
    }

    /**
     * Get metadata for all registered agents.
     *
     * @return array<string, array<string, mixed>>
     */
    public function metadata(): array
    {
        $metadata = [];

        foreach ($this->supports as $identifier => $support) {
            $metadata[$identifier] = [
                'identifier' => $support->identifier(),
                'name' => $support->name(),
                'description' => $support->description(),
                'spec_version' => $support->specVersion(),
                'capabilities' => $support->getCapabilities(),
                'root_files' => $support->getRootFiles(),
                'documentation_url' => $support->getDocumentationUrl(),
                'priority' => $support->priority(),
            ];
        }

        return $metadata;
    }

    /**
     * Get options array for prompts (identifier => name).
     *
     * @return array<string, string>
     */
    public function getPromptOptions(): array
    {
        $options = [];

        foreach ($this->allByPriority() as $support) {
            $options[$support->identifier()] = $support->name();
        }

        return $options;
    }

    /**
     * Install multiple agent supports.
     *
     * @param  array<string>  $identifiers
     * @param  array<string, mixed>  $options
     * @return array<string, array<string, mixed>>
     */
    public function installMultiple(string $projectPath, array $identifiers, array $options = []): array
    {
        $results = [];

        foreach ($identifiers as $identifier) {
            $support = $this->get($identifier);
            if ($support !== null) {
                $results[$identifier] = $support->install($projectPath, $options);
            } else {
                $results[$identifier] = [
                    'success' => false,
                    'error' => "Agent support '{$identifier}' not found",
                ];
            }
        }

        return $results;
    }

    /**
     * Sync all installed agent supports.
     *
     * @return array<string, array<string, mixed>>
     */
    public function syncAll(string $projectPath): array
    {
        $results = [];

        foreach ($this->installed($projectPath) as $identifier => $support) {
            $results[$identifier] = $support->sync($projectPath);
        }

        return $results;
    }

    /**
     * Get count of registered supports.
     */
    public function count(): int
    {
        return count($this->supports);
    }
}
