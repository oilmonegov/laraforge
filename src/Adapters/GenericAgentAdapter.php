<?php

declare(strict_types=1);

namespace LaraForge\Adapters;

use LaraForge\Adapters\Contracts\AgentAdapterInterface;
use LaraForge\Agents\Contracts\TaskInterface;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillInterface;
use LaraForge\Skills\SkillRegistry;

/**
 * Generic adapter for any AI agent or CLI usage.
 *
 * Provides a standard interface without specific agent integrations.
 */
class GenericAgentAdapter implements AgentAdapterInterface
{
    public function __construct(
        private readonly SkillRegistry $skills,
    ) {}

    public function identifier(): string
    {
        return 'generic';
    }

    public function name(): string
    {
        return 'Generic Agent Adapter';
    }

    public function isAvailable(): bool
    {
        return true; // Always available as fallback
    }

    public function executeSkill(SkillInterface $skill, array $params, ProjectContext $context): array
    {
        $result = $skill->execute($params);

        return [
            'success' => $result->isSuccess(),
            'output' => $result->output(),
            'artifacts' => $result->artifacts(),
            'next_steps' => $result->nextSteps(),
            'error' => $result->error(),
            'metadata' => $result->metadata(),
        ];
    }

    public function executeTask(TaskInterface $task, ProjectContext $context): array
    {
        return [
            'success' => true,
            'output' => $this->formatTaskDescription($task),
            'task' => $task->toArray(),
        ];
    }

    public function getContextMetadata(): array
    {
        return [
            'adapter' => $this->identifier(),
            'skills' => array_keys($this->skills->all()),
            'categories' => $this->skills->categories(),
            'capabilities' => [
                'parallel_execution' => false,
                'worktree_support' => true,
                'mcp_integration' => false,
            ],
        ];
    }

    public function formatOutput(mixed $output): string
    {
        if (is_string($output)) {
            return $output;
        }

        if (is_array($output)) {
            return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return (string) $output;
    }

    /**
     * Get available skills as a simple list.
     *
     * @return array<string, string>
     */
    public function listSkills(): array
    {
        $skills = [];

        foreach ($this->skills->all() as $skill) {
            $skills[$skill->identifier()] = $skill->description();
        }

        return $skills;
    }

    /**
     * Get skill documentation in markdown format.
     */
    public function getSkillDocumentation(string $skillId): ?string
    {
        $skill = $this->skills->get($skillId);

        if (! $skill) {
            return null;
        }

        $doc = "# {$skill->name()}\n\n";
        $doc .= "**Identifier:** `{$skill->identifier()}`\n";
        $doc .= "**Category:** {$skill->category()}\n\n";
        $doc .= "## Description\n\n{$skill->description()}\n\n";

        $tags = $skill->tags();
        if (! empty($tags)) {
            $doc .= '**Tags:** '.implode(', ', $tags)."\n\n";
        }

        $params = $skill->parameters();
        if (! empty($params)) {
            $doc .= "## Parameters\n\n";
            foreach ($params as $name => $spec) {
                $required = ($spec['required'] ?? false) ? '(required)' : '(optional)';
                $doc .= "### `{$name}` {$required}\n\n";
                $doc .= "- **Type:** {$spec['type']}\n";
                $doc .= "- **Description:** {$spec['description']}\n";
                if (isset($spec['default'])) {
                    $doc .= '- **Default:** `'.json_encode($spec['default'])."`\n";
                }
                $doc .= "\n";
            }
        }

        $doc .= "## Usage\n\n";
        $doc .= "```bash\n";
        $doc .= "laraforge skill:run {$skill->identifier()}";
        foreach ($params as $name => $spec) {
            if ($spec['required'] ?? false) {
                $doc .= " --params {$name}=<value>";
            }
        }
        $doc .= "\n```\n";

        return $doc;
    }

    /**
     * Generate a simple help text for all skills.
     */
    public function generateHelpText(): string
    {
        $help = "# LaraForge Skills\n\n";

        $categories = $this->skills->categories();
        foreach ($categories as $category) {
            $categorySkills = $this->skills->byCategory($category);
            if (empty($categorySkills)) {
                continue;
            }

            $help .= '## '.ucfirst($category)."\n\n";
            foreach ($categorySkills as $skill) {
                $help .= "- **{$skill->identifier()}**: {$skill->description()}\n";
            }
            $help .= "\n";
        }

        return $help;
    }

    private function formatTaskDescription(TaskInterface $task): string
    {
        $output = "Task: {$task->title()}\n";
        $output .= "Type: {$task->type()}\n";
        $output .= "Status: {$task->status()}\n";

        if ($task->description()) {
            $output .= "\nDescription:\n{$task->description()}\n";
        }

        return $output;
    }
}
