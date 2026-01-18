<?php

declare(strict_types=1);

namespace LaraForge\AgentSupport\Agents;

use LaraForge\AgentSupport\AbstractAgentSupport;

/**
 * Claude Code Agent Support
 *
 * Provides comprehensive integration with Claude Code, including:
 * - CLAUDE.md main configuration file
 * - Skills (slash commands like /commit, /review-pr)
 * - Commands (custom CLI command integrations)
 * - Sub-agents (specialized Task tool agents)
 */
final class ClaudeCodeSupport extends AbstractAgentSupport
{
    public function identifier(): string
    {
        return 'claude-code';
    }

    public function name(): string
    {
        return 'Claude Code';
    }

    public function description(): string
    {
        return 'Integration with Anthropic\'s Claude Code CLI tool, supporting CLAUDE.md, skills, commands, and sub-agents.';
    }

    public function specVersion(): string
    {
        return '2025.01';
    }

    public function isApplicable(string $projectPath): bool
    {
        // Claude Code is applicable to any project
        return true;
    }

    /**
     * @return array<string>
     */
    public function getRootFiles(): array
    {
        return ['CLAUDE.md'];
    }

    /**
     * @return array<string, string>
     */
    public function getDirectoryStructure(): array
    {
        return [
            'skills' => 'Skill definitions (slash commands)',
            'commands' => 'CLI command integrations',
            'agents' => 'Sub-agent configurations',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getCapabilities(): array
    {
        return [
            'skills' => true,
            'commands' => true,
            'sub_agents' => true,
            'mcp_server' => true,
            'hooks' => true,
            'parallel_execution' => true,
            'worktree_support' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function install(string $projectPath, array $options = []): array
    {
        $createdFiles = [];
        $messages = [];

        // Load project context
        $context = $this->loadProjectContext($projectPath);
        $docs = $this->getDocumentationPaths($projectPath);
        $criteria = $this->getCriteriaFiles($projectPath);

        // Build full context
        $fullContext = array_merge($context, [
            'docs' => $docs,
            'criteria' => $criteria,
            'docReferences' => $this->formatDocReferences($docs),
            'criteriaFiles' => $criteria,
            'generatedAt' => $this->currentTimestamp(),
        ]);

        // Create CLAUDE.md in project root
        $claudeMdPath = $projectPath.'/CLAUDE.md';
        $claudeMdContent = $this->generateMainConfig($fullContext);
        $this->writeFile($claudeMdPath, $claudeMdContent, $createdFiles);
        $messages[] = 'Created CLAUDE.md with project documentation references';

        // Create agent directory structure
        $agentDir = $this->ensureAgentDirectory($projectPath);

        // Create skills directory with sample skills
        $skillsDir = $agentDir.'/skills';
        $this->filesystem->mkdir($skillsDir);
        $this->createDefaultSkills($skillsDir, $createdFiles, $messages);

        // Create commands directory
        $commandsDir = $agentDir.'/commands';
        $this->filesystem->mkdir($commandsDir);
        $this->createDefaultCommands($commandsDir, $createdFiles, $messages);

        // Create agents directory with sub-agent configs
        $agentsDir = $agentDir.'/agents';
        $this->filesystem->mkdir($agentsDir);
        $this->createDefaultAgents($agentsDir, $createdFiles, $messages);

        return [
            'success' => true,
            'files_created' => $createdFiles,
            'messages' => $messages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function uninstall(string $projectPath): array
    {
        $removedFiles = [];
        $messages = [];

        // Remove CLAUDE.md
        $this->removeFile($projectPath.'/CLAUDE.md', $removedFiles);

        // Remove agent directory
        $agentDir = $this->getAgentDirectory($projectPath);
        if ($this->filesystem->exists($agentDir)) {
            $this->filesystem->remove($agentDir);
            $removedFiles[] = $agentDir;
            $messages[] = 'Removed Claude Code agent directory';
        }

        return [
            'success' => true,
            'files_removed' => $removedFiles,
            'messages' => $messages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(string $projectPath): array
    {
        $updatedFiles = [];
        $messages = [];

        // Reload project context
        $context = $this->loadProjectContext($projectPath);
        $docs = $this->getDocumentationPaths($projectPath);
        $criteria = $this->getCriteriaFiles($projectPath);

        // Build full context
        $fullContext = array_merge($context, [
            'docs' => $docs,
            'criteria' => $criteria,
            'docReferences' => $this->formatDocReferences($docs),
            'criteriaFiles' => $criteria,
            'generatedAt' => $this->currentTimestamp(),
        ]);

        // Regenerate CLAUDE.md
        $claudeMdPath = $projectPath.'/CLAUDE.md';
        $claudeMdContent = $this->generateMainConfig($fullContext);
        $this->filesystem->dumpFile($claudeMdPath, $claudeMdContent);
        $updatedFiles[] = $claudeMdPath;
        $messages[] = 'Updated CLAUDE.md with latest documentation references';

        return [
            'success' => true,
            'files_updated' => $updatedFiles,
            'messages' => $messages,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function generateMainConfig(array $context): string
    {
        $projectName = $context['project']['name'] ?? basename(getcwd() ?: '.');
        $projectDescription = $context['project']['description'] ?? 'A Laravel application';
        $framework = $context['framework'] ?? 'laravel';
        $docReferences = $context['docReferences'] ?? 'No documentation files found yet.';
        $generatedAt = $context['generatedAt'] ?? $this->currentTimestamp();

        // Build criteria section
        $criteriaSection = '';
        if (! empty($context['criteriaFiles'])) {
            $criteriaSection = "\n### Acceptance Criteria\n\n";
            foreach ($context['criteriaFiles'] as $file) {
                $criteriaSection .= "- [{$file}]({$file})\n";
            }
        }

        return <<<MARKDOWN
# {$projectName}

{$projectDescription}

> Generated by LaraForge - Last synced: {$generatedAt}

## Project Documentation

{$docReferences}
{$criteriaSection}

## Development Guidelines

### Framework
This project uses **{$framework}** as its primary framework.

### Code Quality Standards
- PHPStan Level 8 for static analysis
- Pest PHP for testing (unit, feature, architecture)
- Laravel Pint for code style
- Conventional commits for version control

### File Organization
```
app/
├── Actions/           # Single-purpose action classes
├── Contracts/         # Interfaces
├── DTOs/              # Data Transfer Objects
├── Enums/             # PHP enums
├── Events/            # Domain events
├── Exceptions/        # Custom exceptions
├── Http/              # Controllers, Middleware, Requests, Resources
├── Jobs/              # Queue jobs
├── Listeners/         # Event listeners
├── Models/            # Eloquent models
├── Policies/          # Authorization policies
├── Services/          # Business logic services
└── Support/           # Helpers and utilities
```

## LaraForge Skills

Use these skills via slash commands:

- `/laraforge init` - Initialize LaraForge in a project
- `/laraforge generate <type>` - Generate code from templates
- `/laraforge criteria:init` - Initialize acceptance criteria
- `/laraforge criteria:validate` - Validate criteria status
- `/laraforge hooks:install` - Install git hooks
- `/laraforge prd:import` - Import PRD from file
- `/laraforge next` - Get next recommended action

## Testing Commands

```bash
# Run all tests
./vendor/bin/pest

# Run with coverage
./vendor/bin/pest --coverage

# Run architecture tests
./vendor/bin/pest --group=arch

# Run static analysis
./vendor/bin/phpstan analyse

# Fix code style
./vendor/bin/pint
```

## Git Workflow

### Branch Naming
- `feature/ABC-123-description` - New features
- `bugfix/ABC-456-description` - Bug fixes
- `hotfix/ABC-789-description` - Urgent fixes
- `refactor/ABC-012-description` - Refactoring

### Commit Format
```
type(scope): description

[optional body]

[optional footer]
```

Types: feat, fix, docs, style, refactor, perf, test, build, ci, chore

## Security Guidelines

Before committing, verify:
- No hardcoded credentials or secrets
- All user inputs are validated
- SQL queries use parameter binding
- CSRF protection is in place
- Sensitive data is not logged

## AI Agent Instructions

### When Implementing Features
1. Read existing code first to understand patterns
2. Follow established conventions in the codebase
3. Write tests alongside implementation
4. Keep changes focused and minimal
5. Reference the PRD and FRD for requirements

### When Fixing Bugs
1. Write a failing test first
2. Make the minimal fix
3. Verify the test passes
4. Check for regression in related areas

### Forbidden Actions
- Never commit to main/master directly
- Never remove or bypass tests
- Never ignore static analysis errors
- Never hardcode environment values
- Never store secrets in code

---

*Managed by LaraForge - See `.laraforge/` for configuration*
MARKDOWN;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSkillFormat(): array
    {
        return [
            'format' => 'markdown',
            'location' => '.laraforge/agents/claude-code/skills/',
            'naming' => '{skill-name}.md',
            'structure' => [
                'name' => 'Skill name (displayed in /help)',
                'description' => 'What the skill does',
                'trigger' => 'Slash command to invoke (e.g., /skill-name)',
                'instructions' => 'Detailed instructions for Claude',
                'examples' => 'Usage examples',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $skill
     */
    public function formatSkill(array $skill): string
    {
        $name = $skill['name'] ?? 'Unnamed Skill';
        $description = $skill['description'] ?? '';
        $trigger = $skill['trigger'] ?? '';
        $instructions = $skill['instructions'] ?? '';
        $examples = $skill['examples'] ?? [];

        $content = "# {$name}\n\n";

        if ($description) {
            $content .= "{$description}\n\n";
        }

        if ($trigger) {
            $content .= "**Trigger:** `{$trigger}`\n\n";
        }

        if ($instructions) {
            $content .= "## Instructions\n\n{$instructions}\n\n";
        }

        if (! empty($examples)) {
            $content .= "## Examples\n\n";
            foreach ($examples as $example) {
                $content .= "```\n{$example}\n```\n\n";
            }
        }

        return $content;
    }

    public function getDocumentationUrl(): string
    {
        return 'https://docs.anthropic.com/en/docs/claude-code';
    }

    public function priority(): int
    {
        return 100; // Highest priority - primary supported agent
    }

    /**
     * Create default skills for Claude Code.
     *
     * @param  array<string>  $createdFiles
     * @param  array<string>  $messages
     */
    private function createDefaultSkills(string $skillsDir, array &$createdFiles, array &$messages): void
    {
        // Code Review Skill
        $codeReviewSkill = <<<'MARKDOWN'
# Code Review

Performs comprehensive code review with focus on security, performance, and Laravel best practices.

**Trigger:** `/code-review`

## Instructions

When triggered, perform a thorough code review:

1. **Security Analysis**
   - Check for SQL injection vulnerabilities
   - Verify input validation and sanitization
   - Look for XSS vulnerabilities
   - Check authentication and authorization
   - Verify CSRF protection

2. **Performance Review**
   - Identify N+1 query problems
   - Check for missing indexes
   - Look for inefficient loops
   - Review caching opportunities

3. **Code Quality**
   - Verify type declarations
   - Check for code duplication
   - Review naming conventions
   - Validate error handling

4. **Laravel Best Practices**
   - Use of appropriate Laravel features
   - Proper use of Eloquent relationships
   - Middleware usage
   - Service container usage

## Output Format

Provide findings in this format:

```
## Code Review Results

### Critical Issues
- [Issue description with file:line reference]

### Warnings
- [Warning description with file:line reference]

### Suggestions
- [Improvement suggestion with context]

### Positive Observations
- [Good practices found]
```

## Examples

```
/code-review app/Http/Controllers/UserController.php
/code-review --focus=security app/Services/
/code-review --diff HEAD~5
```
MARKDOWN;

        $this->writeFile($skillsDir.'/code-review.md', $codeReviewSkill, $createdFiles);

        // Feature Implementation Skill
        $featureSkill = <<<'MARKDOWN'
# Feature Implementation

Guides implementation of features following LaraForge workflow and project documentation.

**Trigger:** `/implement-feature`

## Instructions

When triggered with a feature name or description:

1. **Requirements Check**
   - Read the PRD at `.laraforge/docs/prd.md` (if exists)
   - Read the FRD at `.laraforge/docs/frd.yaml` (if exists)
   - Check acceptance criteria at `.laraforge/criteria/`

2. **Planning Phase**
   - Identify affected files and components
   - Design the implementation approach
   - Create a step-by-step plan

3. **Implementation Phase**
   - Follow existing patterns in the codebase
   - Write tests alongside implementation
   - Use strict types and proper type declarations
   - Follow Laravel conventions

4. **Verification Phase**
   - Run tests: `./vendor/bin/pest`
   - Run static analysis: `./vendor/bin/phpstan analyse`
   - Run code style: `./vendor/bin/pint --test`

## Usage

```
/implement-feature "user authentication with 2FA"
/implement-feature --from-frd feature-name
/implement-feature --criteria AC-001
```
MARKDOWN;

        $this->writeFile($skillsDir.'/implement-feature.md', $featureSkill, $createdFiles);

        // Test Generation Skill
        $testSkill = <<<'MARKDOWN'
# Test Generation

Generates comprehensive Pest PHP tests following project standards.

**Trigger:** `/generate-tests`

## Instructions

When triggered for a class or feature:

1. **Analyze the Target**
   - Read the source class/file
   - Understand its dependencies
   - Identify public methods and behaviors

2. **Test Structure**
   - Use Pest PHP syntax
   - Follow AAA pattern (Arrange-Act-Assert)
   - Use descriptive test names

3. **Test Coverage**
   - Happy path scenarios
   - Edge cases and boundary conditions
   - Error conditions and exceptions
   - Authorization checks (if applicable)

4. **Test Organization**
   ```php
   describe('ClassName', function () {
       describe('methodName', function () {
           it('does expected behavior', function () {
               // Arrange
               // Act
               // Assert
           });
       });
   });
   ```

## Usage

```
/generate-tests App/Services/PaymentService
/generate-tests --type=feature App/Http/Controllers/UserController
/generate-tests --coverage=high App/Models/User
```
MARKDOWN;

        $this->writeFile($skillsDir.'/generate-tests.md', $testSkill, $createdFiles);

        $messages[] = 'Created default skills: code-review, implement-feature, generate-tests';
    }

    /**
     * Create default commands for Claude Code.
     *
     * @param  array<string>  $createdFiles
     * @param  array<string>  $messages
     */
    private function createDefaultCommands(string $commandsDir, array &$createdFiles, array &$messages): void
    {
        $laraforgeCommands = <<<'MARKDOWN'
# LaraForge CLI Commands

Reference for LaraForge CLI commands available in this project.

## Core Commands

### Initialize Project
```bash
./vendor/bin/laraforge init
```
Initializes LaraForge in the current project with interactive configuration.

### Generate Code
```bash
./vendor/bin/laraforge generate <type> [name] [options]
```

Available generators:
- `git-hooks` - Git hooks for pre-commit, commit-msg, pre-push
- `api-resource` - API Resource and Collection classes
- `feature-test` - Feature test with HTTP client
- `policy` - Authorization policy class
- `manager` - Manager pattern with multiple drivers

### List Generators
```bash
./vendor/bin/laraforge generators
```
Shows all available generators with descriptions.

## Documentation Commands

### Import PRD
```bash
./vendor/bin/laraforge prd:import <file>
```
Imports a Product Requirements Document from file.

### Initialize Criteria
```bash
./vendor/bin/laraforge criteria:init
```
Creates acceptance criteria YAML structure.

### Validate Criteria
```bash
./vendor/bin/laraforge criteria:validate
```
Validates acceptance criteria status.

## Utility Commands

### Install Git Hooks
```bash
./vendor/bin/laraforge hooks:install
```
Installs git hooks for code quality enforcement.

### Get Next Action
```bash
./vendor/bin/laraforge next
```
Suggests the next recommended action based on project state.

### Reconfigure
```bash
./vendor/bin/laraforge reconfigure
```
Reconfigures LaraForge settings after initial setup.

### Version
```bash
./vendor/bin/laraforge --version
```
Shows LaraForge version.
MARKDOWN;

        $this->writeFile($commandsDir.'/laraforge-commands.md', $laraforgeCommands, $createdFiles);
        $messages[] = 'Created LaraForge commands reference';
    }

    /**
     * Create default sub-agent configurations.
     *
     * @param  array<string>  $createdFiles
     * @param  array<string>  $messages
     */
    private function createDefaultAgents(string $agentsDir, array &$createdFiles, array &$messages): void
    {
        // Code Reviewer Agent
        $codeReviewerAgent = <<<'MARKDOWN'
# Code Reviewer Agent

A specialized sub-agent for performing code reviews.

## Purpose

This agent focuses exclusively on code review tasks, providing detailed analysis
of code quality, security, and adherence to project standards.

## Capabilities

- Security vulnerability detection
- Performance issue identification
- Code style and convention checking
- Laravel best practices validation
- Test coverage analysis

## Usage with Task Tool

```
Use the Task tool with subagent_type: "code-reviewer" for:
- Pull request reviews
- Pre-commit code analysis
- Security audits
- Performance reviews
```

## Configuration

This agent should:
1. Focus only on code review tasks
2. Provide actionable feedback with file:line references
3. Categorize issues by severity (critical, warning, info)
4. Include positive observations alongside issues
5. Reference project standards from CLAUDE.md

## Example Task

```json
{
  "subagent_type": "code-reviewer",
  "prompt": "Review the changes in the last commit for security issues",
  "description": "Security review of recent changes"
}
```
MARKDOWN;

        $this->writeFile($agentsDir.'/code-reviewer.md', $codeReviewerAgent, $createdFiles);

        // Feature Developer Agent
        $featureDevAgent = <<<'MARKDOWN'
# Feature Developer Agent

A specialized sub-agent for implementing new features.

## Purpose

This agent handles feature implementation from requirements to working code,
following the project's established patterns and standards.

## Capabilities

- Requirements analysis from PRD/FRD
- Implementation planning
- Code generation following patterns
- Test creation alongside implementation
- Documentation updates

## Usage with Task Tool

```
Use the Task tool with subagent_type: "feature-developer" for:
- New feature implementation
- Feature enhancements
- Complex refactoring with new functionality
```

## Configuration

This agent should:
1. Always read requirements documents first
2. Plan implementation before coding
3. Write tests alongside code
4. Follow existing codebase patterns
5. Keep changes focused and minimal

## Example Task

```json
{
  "subagent_type": "feature-developer",
  "prompt": "Implement the user profile feature as described in FRD",
  "description": "Implement user profile feature"
}
```
MARKDOWN;

        $this->writeFile($agentsDir.'/feature-developer.md', $featureDevAgent, $createdFiles);

        // Test Writer Agent
        $testWriterAgent = <<<'MARKDOWN'
# Test Writer Agent

A specialized sub-agent for writing comprehensive tests.

## Purpose

This agent creates thorough test suites using Pest PHP, ensuring
proper coverage of functionality, edge cases, and error conditions.

## Capabilities

- Unit test generation
- Feature/integration test creation
- Architecture test definitions
- Test data and factory creation
- Mock and stub setup

## Usage with Task Tool

```
Use the Task tool with subagent_type: "test-writer" for:
- Creating test suites for new features
- Improving test coverage
- Writing regression tests for bug fixes
```

## Configuration

This agent should:
1. Use Pest PHP syntax exclusively
2. Follow AAA pattern (Arrange-Act-Assert)
3. Include happy path, edge cases, and error conditions
4. Use descriptive test names
5. Leverage factories and test helpers

## Example Task

```json
{
  "subagent_type": "test-writer",
  "prompt": "Create comprehensive tests for the PaymentService class",
  "description": "Generate PaymentService tests"
}
```
MARKDOWN;

        $this->writeFile($agentsDir.'/test-writer.md', $testWriterAgent, $createdFiles);

        $messages[] = 'Created sub-agent configurations: code-reviewer, feature-developer, test-writer';
    }
}
