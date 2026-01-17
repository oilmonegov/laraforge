<?php

declare(strict_types=1);

namespace LaraForge\Adapters;

use LaraForge\Adapters\Contracts\AgentAdapterInterface;
use LaraForge\Agents\Contracts\TaskInterface;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillInterface;
use LaraForge\Skills\SkillRegistry;
use LaraForge\Workflows\WorkflowOrchestrator;

/**
 * Adapter for Claude Code integration.
 *
 * Provides deep integration with Claude Code's Task tool, subagent system,
 * and MCP server capabilities.
 */
class ClaudeCodeAdapter implements AgentAdapterInterface
{
    public function __construct(
        private readonly SkillRegistry $skills,
        private readonly ?WorkflowOrchestrator $orchestrator = null,
    ) {}

    public function identifier(): string
    {
        return 'claude-code';
    }

    public function name(): string
    {
        return 'Claude Code Adapter';
    }

    public function isAvailable(): bool
    {
        // Check if running within Claude Code environment
        return getenv('CLAUDE_CODE') !== false
            || file_exists(getenv('HOME').'/.claude');
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
        ];
    }

    public function executeTask(TaskInterface $task, ProjectContext $context): array
    {
        // For Claude Code, we format the task for the AI to process
        $taskDescription = $this->formatTaskForClaudeCode($task, $context);

        return [
            'success' => true,
            'output' => $taskDescription,
            'task' => $task->toArray(),
        ];
    }

    public function getContextMetadata(): array
    {
        return [
            'adapter' => $this->identifier(),
            'skills' => $this->skills->metadata(),
            'capabilities' => [
                'parallel_execution' => true,
                'worktree_support' => true,
                'mcp_integration' => true,
            ],
            'instructions' => $this->getAgentInstructions(),
        ];
    }

    public function formatOutput(mixed $output): string
    {
        if (is_string($output)) {
            return $output;
        }

        if (is_array($output)) {
            return $this->formatArrayOutput($output);
        }

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * Get instructions for Claude Code agent.
     *
     * @return array<string, string>
     */
    public function getAgentInstructions(): array
    {
        return [
            'skill_usage' => <<<'INSTRUCTION'
When working on LaraForge projects:
1. Use `laraforge skill:run <skill>` to execute skills
2. Check `laraforge status` for project state
3. Use `laraforge plan` for recommendations
4. Follow the workflow: PRD → FRD → Design → Implement → Test
INSTRUCTION,

            'document_workflow' => <<<'INSTRUCTION'
Document Creation Workflow:
1. Start with create-prd for high-level requirements
2. Use create-frd for detailed feature requirements with stepwise refinement
3. Generate pseudocode before implementation
4. Create test contracts BEFORE writing test implementations
5. Implement from pseudocode, not ad-hoc
INSTRUCTION,

            'parallel_work' => <<<'INSTRUCTION'
For parallel work with multiple agents:
1. Use `laraforge worktree create` to create isolated workspaces
2. Each agent works in their own worktree
3. Merge with `laraforge worktree merge` when complete
4. Resolve conflicts collaboratively
INSTRUCTION,

            'quality_standards' => <<<'INSTRUCTION'
Quality Standards:
- All features must have FRD with acceptance criteria
- Test contracts define expected behavior, not test implementation
- Pseudocode should be detailed enough to translate directly
- Commit messages follow conventional commit format
INSTRUCTION,
        ];
    }

    /**
     * Generate a skill manifest for MCP server.
     *
     * @return array<string, mixed>
     */
    public function generateMcpManifest(): array
    {
        $tools = [];

        foreach ($this->skills->all() as $skill) {
            $properties = [];
            $required = [];

            foreach ($skill->parameters() as $name => $spec) {
                $properties[$name] = [
                    'type' => $this->mapTypeToJsonSchema($spec['type']),
                    'description' => $spec['description'],
                ];

                if (isset($spec['default'])) {
                    $properties[$name]['default'] = $spec['default'];
                }

                if ($spec['required'] ?? false) {
                    $required[] = $name;
                }
            }

            $tools[] = [
                'name' => 'laraforge_'.$skill->identifier(),
                'description' => $skill->description(),
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ],
            ];
        }

        return [
            'name' => 'laraforge',
            'version' => '1.0.0',
            'description' => 'LaraForge AI Agent Orchestration Framework',
            'tools' => $tools,
        ];
    }

    /**
     * Format a task for Claude Code to process.
     */
    private function formatTaskForClaudeCode(TaskInterface $task, ProjectContext $context): string
    {
        $output = "## Task: {$task->title()}\n\n";
        $output .= "**Type:** {$task->type()}\n";
        $output .= "**Priority:** {$task->priority()}\n";
        $output .= "**Status:** {$task->status()}\n\n";

        if ($task->description()) {
            $output .= "### Description\n\n{$task->description()}\n\n";
        }

        if ($task->params()) {
            $output .= "### Parameters\n\n";
            foreach ($task->params() as $key => $value) {
                $output .= "- **{$key}:** ".json_encode($value)."\n";
            }
            $output .= "\n";
        }

        // Add recommended skills
        $output .= "### Recommended Skills\n\n";
        if ($this->orchestrator) {
            $recommendation = $this->orchestrator->analyze($context);
            $output .= "- Suggested: `{$recommendation->skill}`\n";
            $output .= "- Reason: {$recommendation->message}\n\n";
        }

        $output .= "### Available Commands\n\n";
        $output .= "```bash\n";
        $output .= "laraforge skill:run {$task->type()} --params='...'\n";
        $output .= "laraforge status\n";
        $output .= "laraforge plan\n";
        $output .= "```\n";

        return $output;
    }

    private function formatArrayOutput(array $output): string
    {
        $formatted = '';

        foreach ($output as $key => $value) {
            if (is_numeric($key)) {
                $formatted .= "- {$value}\n";
            } else {
                if (is_array($value)) {
                    $formatted .= "**{$key}:**\n";
                    foreach ($value as $subKey => $subValue) {
                        $formatted .= "  - {$subKey}: {$subValue}\n";
                    }
                } else {
                    $formatted .= "**{$key}:** {$value}\n";
                }
            }
        }

        return $formatted;
    }

    private function mapTypeToJsonSchema(string $type): string
    {
        return match ($type) {
            'int', 'integer' => 'integer',
            'float', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }
}
