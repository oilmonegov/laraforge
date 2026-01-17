<?php

declare(strict_types=1);

namespace LaraForge\Adapters;

use LaraForge\Adapters\Contracts\AgentAdapterInterface;
use LaraForge\Skills\SkillRegistry;
use LaraForge\Workflows\WorkflowOrchestrator;

/**
 * IDE Registry
 *
 * Manages IDE adapter registrations and allows dynamic addition
 * of support for new IDEs at runtime.
 */
final class IdeRegistry
{
    /**
     * @var array<string, AgentAdapterInterface>
     */
    private array $adapters = [];

    /**
     * @var array<string, class-string<AgentAdapterInterface>>
     */
    private array $adapterClasses = [];

    /**
     * Default adapter identifiers bundled with LaraForge.
     *
     * @var array<string, class-string<AgentAdapterInterface>>
     */
    private const BUILTIN_ADAPTERS = [
        'claude-code' => ClaudeCodeAdapter::class,
        'cursor' => CursorAdapter::class,
        'generic' => GenericAgentAdapter::class,
    ];

    public function __construct(
        private readonly SkillRegistry $skills,
        private readonly ?WorkflowOrchestrator $orchestrator = null,
    ) {
        $this->adapterClasses = self::BUILTIN_ADAPTERS;
    }

    /**
     * Register an IDE adapter class.
     *
     * @param  class-string<AgentAdapterInterface>  $adapterClass
     */
    public function registerAdapter(string $identifier, string $adapterClass): void
    {
        $this->adapterClasses[$identifier] = $adapterClass;

        // Clear cached instance if exists
        unset($this->adapters[$identifier]);
    }

    /**
     * Get an adapter by identifier.
     */
    public function get(string $identifier): ?AgentAdapterInterface
    {
        if (! isset($this->adapterClasses[$identifier])) {
            return null;
        }

        // Lazy instantiation
        if (! isset($this->adapters[$identifier])) {
            $class = $this->adapterClasses[$identifier];
            $this->adapters[$identifier] = new $class($this->skills, $this->orchestrator);
        }

        return $this->adapters[$identifier];
    }

    /**
     * Get all registered adapter identifiers.
     *
     * @return array<string>
     */
    public function identifiers(): array
    {
        return array_keys($this->adapterClasses);
    }

    /**
     * Get all instantiated adapters.
     *
     * @return array<string, AgentAdapterInterface>
     */
    public function all(): array
    {
        foreach ($this->adapterClasses as $identifier => $class) {
            $this->get($identifier);
        }

        return $this->adapters;
    }

    /**
     * Check if an adapter is registered.
     */
    public function has(string $identifier): bool
    {
        return isset($this->adapterClasses[$identifier]);
    }

    /**
     * Auto-detect which IDE is being used.
     */
    public function detect(): ?AgentAdapterInterface
    {
        foreach ($this->adapterClasses as $identifier => $class) {
            $adapter = $this->get($identifier);

            if ($adapter?->isAvailable()) {
                return $adapter;
            }
        }

        // Default to generic if nothing detected
        return $this->get('generic');
    }

    /**
     * Get metadata about all available adapters.
     *
     * @return array<string, array<string, mixed>>
     */
    public function metadata(): array
    {
        $metadata = [];

        foreach ($this->adapterClasses as $identifier => $class) {
            $adapter = $this->get($identifier);

            if ($adapter) {
                $metadata[$identifier] = [
                    'name' => $adapter->name(),
                    'available' => $adapter->isAvailable(),
                    'class' => $class,
                ];
            }
        }

        return $metadata;
    }

    /**
     * Unregister an adapter.
     */
    public function unregister(string $identifier): bool
    {
        if (! isset($this->adapterClasses[$identifier])) {
            return false;
        }

        unset($this->adapterClasses[$identifier], $this->adapters[$identifier]);

        return true;
    }

    /**
     * Get configuration for adding IDE support.
     *
     * @return array<string, mixed>
     */
    public function getIdeSetupConfig(string $identifier): array
    {
        $adapter = $this->get($identifier);

        if (! $adapter) {
            return ['error' => "Unknown IDE: {$identifier}"];
        }

        $config = [
            'identifier' => $identifier,
            'name' => $adapter->name(),
            'available' => $adapter->isAvailable(),
            'metadata' => $adapter->getContextMetadata(),
        ];

        // Add IDE-specific configuration files
        $config['files'] = match ($identifier) {
            'claude-code' => $this->getClaudeCodeSetup(),
            'cursor' => $this->getCursorSetup(),
            default => [],
        };

        return $config;
    }

    /**
     * Generate IDE configuration files.
     *
     * @return array<string, string> Map of file paths to content
     */
    public function generateConfigFiles(string $identifier): array
    {
        $adapter = $this->get($identifier);

        if (! $adapter) {
            return [];
        }

        return match ($identifier) {
            'claude-code' => $this->generateClaudeCodeFiles($adapter),
            'cursor' => $this->generateCursorFiles($adapter),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getClaudeCodeSetup(): array
    {
        return [
            'CLAUDE.md' => [
                'description' => 'Claude Code context file for project instructions',
                'required' => true,
            ],
            '.claude/settings.json' => [
                'description' => 'Claude Code settings',
                'required' => false,
            ],
            '.claude/skills/' => [
                'description' => 'Directory for custom Claude Code skills',
                'required' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCursorSetup(): array
    {
        return [
            '.cursorrules' => [
                'description' => 'Cursor AI rules file',
                'required' => true,
            ],
            '.vscode/settings.json' => [
                'description' => 'VS Code/Cursor workspace settings',
                'required' => false,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function generateClaudeCodeFiles(AgentAdapterInterface $adapter): array
    {
        $metadata = $adapter->getContextMetadata();
        $instructions = $metadata['instructions'] ?? [];

        $claudeMd = "# Claude Code Instructions\n\n";
        $claudeMd .= "This project uses LaraForge for AI-assisted development.\n\n";

        foreach ($instructions as $section => $content) {
            $claudeMd .= '## '.ucfirst(str_replace('_', ' ', $section))."\n\n";
            $claudeMd .= $content."\n\n";
        }

        $claudeMd .= "## Available Skills\n\n";
        $claudeMd .= "```bash\n";
        $claudeMd .= "laraforge skill:list  # List all available skills\n";
        $claudeMd .= "laraforge status      # Show project status\n";
        $claudeMd .= "laraforge plan        # Get recommendations\n";
        $claudeMd .= "```\n";

        return [
            'CLAUDE.md' => $claudeMd,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function generateCursorFiles(AgentAdapterInterface $adapter): array
    {
        $files = [];

        // Get Cursor rules if adapter supports it
        if ($adapter instanceof CursorAdapter) {
            $files['.cursorrules'] = $adapter->getCursorRulesContent();

            $settings = $adapter->generateWorkspaceSettings();
            $files['.vscode/settings.json'] = json_encode($settings, JSON_PRETTY_PRINT);
        }

        return $files;
    }

    /**
     * Check for updates to IDE configurations.
     *
     * @return array<string, array<string, mixed>>
     */
    public function checkForUpdates(string $projectPath): array
    {
        $updates = [];

        foreach ($this->adapterClasses as $identifier => $class) {
            $adapter = $this->get($identifier);

            if ($adapter?->isAvailable()) {
                $configFiles = $this->generateConfigFiles($identifier);

                foreach ($configFiles as $relativePath => $content) {
                    $fullPath = $projectPath.'/'.$relativePath;

                    if (file_exists($fullPath)) {
                        $existing = file_get_contents($fullPath);

                        if ($existing !== $content) {
                            $updates[$identifier][$relativePath] = [
                                'action' => 'update',
                                'reason' => 'Configuration has changed',
                            ];
                        }
                    } else {
                        $updates[$identifier][$relativePath] = [
                            'action' => 'create',
                            'reason' => 'File does not exist',
                        ];
                    }
                }
            }
        }

        return $updates;
    }
}
