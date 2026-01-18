<?php

declare(strict_types=1);

namespace LaraForge\AgentSupport\Contracts;

/**
 * Agent Support Interface
 *
 * Defines the contract for IDE/AI coding agent integrations.
 * Each agent (Claude Code, Cursor, JetBrains, Windsurf, etc.) has different
 * configuration formats, skill definitions, and file structures.
 */
interface AgentSupportInterface
{
    /**
     * Get the unique identifier for this agent.
     * Example: 'claude-code', 'cursor', 'jetbrains', 'windsurf'
     */
    public function identifier(): string;

    /**
     * Get the display name for this agent.
     * Example: 'Claude Code', 'Cursor', 'JetBrains IDEs', 'Windsurf'
     */
    public function name(): string;

    /**
     * Get a brief description of this agent support.
     */
    public function description(): string;

    /**
     * Get the version of the agent specification this support targets.
     * Helps track when agent formats change.
     */
    public function specVersion(): string;

    /**
     * Check if this agent is applicable to the current project.
     * May check for existing configuration files or project structure.
     */
    public function isApplicable(string $projectPath): bool;

    /**
     * Check if this agent support is already installed in the project.
     */
    public function isInstalled(string $projectPath): bool;

    /**
     * Install this agent support in the project.
     * Creates necessary files, directories, and configurations.
     *
     * @param  array<string, mixed>  $options  Installation options
     * @return array<string, mixed> Result with 'success', 'files_created', 'messages'
     */
    public function install(string $projectPath, array $options = []): array;

    /**
     * Remove this agent support from the project.
     *
     * @return array<string, mixed> Result with 'success', 'files_removed', 'messages'
     */
    public function uninstall(string $projectPath): array;

    /**
     * Sync/update the agent configuration with latest project state.
     * Called when project documentation or structure changes.
     *
     * @return array<string, mixed> Result with 'success', 'files_updated', 'messages'
     */
    public function sync(string $projectPath): array;

    /**
     * Get the root-level files this agent creates.
     * Example: ['CLAUDE.md'] for Claude Code, ['.cursorrules'] for Cursor
     *
     * @return array<string>
     */
    public function getRootFiles(): array;

    /**
     * Get the directory structure this agent creates under .laraforge/agents/{identifier}/
     *
     * @return array<string, string> Directory => Description
     */
    public function getDirectoryStructure(): array;

    /**
     * Get the capabilities this agent supports.
     *
     * @return array<string, bool>
     */
    public function getCapabilities(): array;

    /**
     * Generate the main configuration file content for this agent.
     * This is the file that references all project documentation.
     *
     * @param  array<string, mixed>  $context  Project context (name, description, docs, etc.)
     */
    public function generateMainConfig(array $context): string;

    /**
     * Get the skill format specification for this agent.
     * Different agents have different ways to define skills.
     *
     * @return array<string, mixed>
     */
    public function getSkillFormat(): array;

    /**
     * Convert a generic skill definition to this agent's format.
     *
     * @param  array<string, mixed>  $skill  Generic skill definition
     */
    public function formatSkill(array $skill): string;

    /**
     * Get documentation URL for this agent's specification.
     */
    public function getDocumentationUrl(): string;

    /**
     * Get the priority for this agent (higher = checked first for applicability).
     */
    public function priority(): int;
}
