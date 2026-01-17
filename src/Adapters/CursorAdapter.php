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
 * Adapter for Cursor IDE integration.
 *
 * Provides integration with Cursor's AI features, Composer,
 * and inline editing capabilities.
 */
class CursorAdapter implements AgentAdapterInterface
{
    public function __construct(
        private readonly SkillRegistry $skills,
        private readonly ?WorkflowOrchestrator $orchestrator = null,
    ) {}

    public function identifier(): string
    {
        return 'cursor';
    }

    public function name(): string
    {
        return 'Cursor IDE Adapter';
    }

    public function isAvailable(): bool
    {
        // Check for Cursor-specific environment or config
        return getenv('CURSOR_IDE') !== false
            || file_exists(getenv('HOME').'/.cursor')
            || $this->hasCursorWorkspaceSettings();
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
            'cursor_actions' => $this->mapToCursorActions($result),
        ];
    }

    public function executeTask(TaskInterface $task, ProjectContext $context): array
    {
        return [
            'success' => true,
            'output' => $this->formatTaskForCursor($task, $context),
            'task' => $task->toArray(),
            'composer_context' => $this->buildComposerContext($task, $context),
        ];
    }

    public function getContextMetadata(): array
    {
        return [
            'adapter' => $this->identifier(),
            'skills' => $this->skills->metadata(),
            'capabilities' => [
                'inline_edit' => true,
                'composer_integration' => true,
                'multi_file_edit' => true,
                'codebase_indexing' => true,
            ],
            'instructions' => $this->getAgentInstructions(),
            'cursor_rules' => $this->getCursorRulesContent(),
        ];
    }

    public function formatOutput(mixed $output): string
    {
        if (is_string($output)) {
            return $output;
        }

        if (is_array($output)) {
            return $this->formatArrayForCursor($output);
        }

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * Get instructions optimized for Cursor's AI.
     *
     * @return array<string, string>
     */
    public function getAgentInstructions(): array
    {
        return [
            'general' => <<<'INSTRUCTION'
When working in Cursor with LaraForge:
1. Use @laraforge to invoke LaraForge skills
2. Reference @workspace for codebase-aware suggestions
3. Use inline edit (Cmd+K) for quick modifications
4. Use Composer for multi-file operations
INSTRUCTION,

            'composer_workflow' => <<<'INSTRUCTION'
Composer Best Practices:
1. Start with high-level intent, let Composer plan
2. Use @Codebase for context-aware generation
3. Review generated code before accepting
4. Chain modifications for related changes
INSTRUCTION,

            'file_operations' => <<<'INSTRUCTION'
File Operations:
- Use @file to reference specific files
- Create new files with Composer "create file" intent
- Modify existing files with inline edit or Composer
- Always validate changes with LaraForge rules
INSTRUCTION,
        ];
    }

    /**
     * Generate .cursorrules content for the project.
     */
    public function getCursorRulesContent(): string
    {
        return <<<'RULES'
# LaraForge Cursor Rules

## Project Structure
- Follow Laravel conventions for file placement
- Use strict types in all PHP files
- Follow PSR-12 coding standards

## Code Generation
- Always use type hints and return types
- Prefer readonly properties and constructor promotion
- Use named arguments for clarity

## Testing
- Generate Pest tests, not PHPUnit
- Use Feature tests for HTTP, Unit tests for isolated logic
- Always include edge cases

## Documentation
- Generate PHPDoc for public methods
- Include @param and @return annotations
- Document complex logic inline

## Security
- Never expose sensitive data
- Validate all user input
- Use prepared statements for database queries
- Sanitize output for XSS prevention

## LaraForge Skills
Available skills:
- create-prd: Create Product Requirements Document
- create-frd: Create Feature Requirements Document
- create-pseudocode: Generate pseudocode from FRD
- implement: Implement from pseudocode
- validate-tests: Validate tests against contracts
RULES;
    }

    /**
     * Generate Cursor workspace settings.
     *
     * @return array<string, mixed>
     */
    public function generateWorkspaceSettings(): array
    {
        return [
            'editor.formatOnSave' => true,
            'editor.defaultFormatter' => 'esbenp.prettier-vscode',
            'php.suggest.basic' => false,
            'laraforge.enabled' => true,
            'laraforge.skills' => array_keys($this->skills->all()),
            'files.associations' => [
                '*.stub' => 'blade',
                '*.yaml.stub' => 'yaml',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapToCursorActions(mixed $result): array
    {
        $actions = [];

        if (method_exists($result, 'artifacts')) {
            foreach ($result->artifacts() as $path => $content) {
                $actions[] = [
                    'type' => 'create_file',
                    'path' => $path,
                    'content' => $content,
                ];
            }
        }

        if (method_exists($result, 'nextSteps')) {
            foreach ($result->nextSteps() as $step) {
                $actions[] = [
                    'type' => 'suggest_skill',
                    'skill' => $step['skill'] ?? '',
                    'reason' => $step['reason'] ?? '',
                ];
            }
        }

        return $actions;
    }

    private function formatTaskForCursor(TaskInterface $task, ProjectContext $context): string
    {
        $output = "## Task: {$task->title()}\n\n";
        $output .= "**Type:** {$task->type()}\n";
        $output .= "**Priority:** {$task->priority()}\n\n";

        if ($task->description()) {
            $output .= "### Description\n\n{$task->description()}\n\n";
        }

        $output .= "### Cursor Actions\n\n";
        $output .= "1. Use @Codebase to understand related code\n";
        $output .= "2. Create/modify files as needed\n";
        $output .= "3. Run tests to verify changes\n\n";

        if ($this->orchestrator) {
            $recommendation = $this->orchestrator->analyze($context);
            $output .= "### Recommended Skill\n\n";
            $output .= "- **{$recommendation->skill}**: {$recommendation->message}\n";
        }

        return $output;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComposerContext(TaskInterface $task, ProjectContext $context): array
    {
        return [
            'intent' => $task->title(),
            'type' => $task->type(),
            'files_to_reference' => $this->getRelevantFiles($task, $context),
            'laraforge_skill' => $task->type(),
        ];
    }

    /**
     * @return array<string>
     */
    private function getRelevantFiles(TaskInterface $task, ProjectContext $context): array
    {
        $files = [];

        // Add relevant files based on task type
        $type = $task->type();

        if (str_contains($type, 'model') || str_contains($type, 'eloquent')) {
            $files[] = 'app/Models/*.php';
        }

        if (str_contains($type, 'controller') || str_contains($type, 'api')) {
            $files[] = 'app/Http/Controllers/*.php';
            $files[] = 'routes/api.php';
            $files[] = 'routes/web.php';
        }

        if (str_contains($type, 'test')) {
            $files[] = 'tests/**/*.php';
        }

        return $files;
    }

    /**
     * @param  array<mixed>  $output
     */
    private function formatArrayForCursor(array $output): string
    {
        $formatted = '';

        foreach ($output as $key => $value) {
            if (is_array($value)) {
                $formatted .= "### {$key}\n\n";
                $formatted .= $this->formatArrayForCursor($value);
            } else {
                $formatted .= "- **{$key}:** {$value}\n";
            }
        }

        return $formatted;
    }

    private function hasCursorWorkspaceSettings(): bool
    {
        $vscodePath = getcwd().'/.vscode/settings.json';

        if (file_exists($vscodePath)) {
            $settings = json_decode((string) file_get_contents($vscodePath), true);

            return isset($settings['cursor.enabled']) || isset($settings['cursor.rules']);
        }

        return false;
    }
}
