<?php

declare(strict_types=1);

namespace LaraForge\AgentSupport\Agents;

use LaraForge\AgentSupport\AbstractAgentSupport;

/**
 * Cursor IDE Agent Support
 *
 * Provides integration with Cursor IDE, including:
 * - .cursorrules configuration file
 * - Project-specific AI rules
 * - Documentation references
 */
final class CursorSupport extends AbstractAgentSupport
{
    public function identifier(): string
    {
        return 'cursor';
    }

    public function name(): string
    {
        return 'Cursor';
    }

    public function description(): string
    {
        return 'Integration with Cursor IDE, supporting .cursorrules for AI-assisted development.';
    }

    public function specVersion(): string
    {
        return '2025.01';
    }

    public function isApplicable(string $projectPath): bool
    {
        // Cursor is applicable to any project
        return true;
    }

    /**
     * @return array<string>
     */
    public function getRootFiles(): array
    {
        return ['.cursorrules'];
    }

    /**
     * @return array<string, string>
     */
    public function getDirectoryStructure(): array
    {
        return [
            'rules' => 'Additional rule files for specific contexts',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getCapabilities(): array
    {
        return [
            'rules' => true,
            'context_files' => true,
            'codebase_indexing' => true,
            'chat_integration' => true,
            'inline_completion' => true,
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

        // Create .cursorrules in project root
        $cursorRulesPath = $projectPath.'/.cursorrules';
        $cursorRulesContent = $this->generateMainConfig($fullContext);
        $this->writeFile($cursorRulesPath, $cursorRulesContent, $createdFiles);
        $messages[] = 'Created .cursorrules with project guidelines';

        // Create agent directory structure
        $agentDir = $this->ensureAgentDirectory($projectPath);

        // Create rules directory with additional rules
        $rulesDir = $agentDir.'/rules';
        $this->filesystem->mkdir($rulesDir);
        $this->createAdditionalRules($rulesDir, $createdFiles, $messages);

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

        // Remove .cursorrules
        $this->removeFile($projectPath.'/.cursorrules', $removedFiles);

        // Remove agent directory
        $agentDir = $this->getAgentDirectory($projectPath);
        if ($this->filesystem->exists($agentDir)) {
            $this->filesystem->remove($agentDir);
            $removedFiles[] = $agentDir;
            $messages[] = 'Removed Cursor agent directory';
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

        // Regenerate .cursorrules
        $cursorRulesPath = $projectPath.'/.cursorrules';
        $cursorRulesContent = $this->generateMainConfig($fullContext);
        $this->filesystem->dumpFile($cursorRulesPath, $cursorRulesContent);
        $updatedFiles[] = $cursorRulesPath;
        $messages[] = 'Updated .cursorrules with latest documentation references';

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

        // Build documentation section
        $docsSection = '';
        if (! empty($context['docs'])) {
            $docsSection .= "Project Documentation:\n";
            foreach ($context['docs'] as $type => $path) {
                if ($path !== null) {
                    $label = str_replace('_', ' ', ucfirst($type));
                    $docsSection .= "- {$label}: {$path}\n";
                }
            }
        }

        // Build criteria section
        $criteriaSection = '';
        if (! empty($context['criteriaFiles'])) {
            $criteriaSection .= "\nAcceptance Criteria Files:\n";
            foreach ($context['criteriaFiles'] as $file) {
                $criteriaSection .= "- {$file}\n";
            }
        }

        return <<<RULES
# {$projectName} - Cursor Rules
# {$projectDescription}
# Framework: {$framework}
# Generated by LaraForge

{$docsSection}{$criteriaSection}

## Code Style & Standards

You are working on a Laravel/PHP project. Follow these guidelines:

### PHP Standards
- Always use `declare(strict_types=1);` at the top of PHP files
- Use typed properties and method signatures
- Prefer `final` classes and `readonly` properties
- Follow PSR-12 coding standards
- Use Laravel Pint for formatting

### Class Structure
```php
<?php

declare(strict_types=1);

namespace App\Services;

final readonly class ExampleService
{
    public function __construct(
        private DependencyInterface \$dependency,
    ) {}

    public function doSomething(string \$input): ResultDTO
    {
        // Implementation
    }
}
```

### Naming Conventions
- Classes: PascalCase (UserRepository)
- Methods: camelCase (findByEmail)
- Variables: camelCase (\$userCount)
- Constants: SCREAMING_SNAKE (MAX_ATTEMPTS)
- Database tables: snake_case, plural (user_profiles)
- Database columns: snake_case (created_at)

## Architecture Patterns

### Recommended Patterns
- Repository Pattern for complex data access
- Action Classes for single-purpose operations
- Service Classes for business logic coordination
- DTOs for data transfer between layers
- Events/Listeners for decoupled side effects

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

## Security Requirements

Always ensure:
- Input validation at system boundaries
- SQL queries use parameter binding (never raw queries)
- CSRF protection on state-changing requests
- Mass assignment protection (\$fillable or \$guarded)
- No hardcoded credentials or secrets
- Sensitive data is never logged

## Testing Guidelines

Use Pest PHP with AAA pattern:
```php
it('does expected behavior', function () {
    // Arrange
    \$data = [...];

    // Act
    \$result = doSomething(\$data);

    // Assert
    expect(\$result)->toBe(...);
});
```

Run tests: `./vendor/bin/pest`
Run static analysis: `./vendor/bin/phpstan analyse`
Run code style: `./vendor/bin/pint`

## Git Workflow

Branch naming:
- feature/ABC-123-description
- bugfix/ABC-456-description
- hotfix/ABC-789-description

Commit format (Conventional Commits):
```
type(scope): description

[body]

[footer]
```

Types: feat, fix, docs, style, refactor, perf, test, build, ci, chore

## Package Selection Priority

1. Laravel First-Party Packages (Sanctum, Horizon, Scout)
2. Spatie Packages (laravel-permission, laravel-activitylog)
3. Well-maintained community packages (>1000 stars, recent updates)
4. Custom implementation (last resort)

## AI Assistant Rules

When implementing features:
1. Read existing code first to understand patterns
2. Follow established conventions in the codebase
3. Write tests alongside implementation
4. Keep changes focused and minimal
5. Don't refactor unrelated code

When fixing bugs:
1. Write a failing test first
2. Make the minimal fix
3. Verify the test passes
4. Check for regression in related areas

Forbidden actions:
- Never commit to main/master directly
- Never remove or bypass tests
- Never ignore static analysis errors
- Never hardcode environment values
- Never store secrets in code
RULES;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSkillFormat(): array
    {
        return [
            'format' => 'rules',
            'location' => '.cursorrules',
            'naming' => 'inline',
            'structure' => [
                'description' => 'Rules are defined as text blocks in .cursorrules',
                'sections' => 'Code style, Architecture, Security, Testing, Git workflow',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $skill
     */
    public function formatSkill(array $skill): string
    {
        $name = $skill['name'] ?? 'Unnamed Rule';
        $description = $skill['description'] ?? '';
        $rules = $skill['rules'] ?? [];

        $content = "## {$name}\n\n";

        if ($description) {
            $content .= "{$description}\n\n";
        }

        if (! empty($rules)) {
            foreach ($rules as $rule) {
                $content .= "- {$rule}\n";
            }
        }

        return $content;
    }

    public function getDocumentationUrl(): string
    {
        return 'https://docs.cursor.com/context/rules-for-ai';
    }

    public function priority(): int
    {
        return 80; // High priority, common IDE choice
    }

    /**
     * Create additional rules for specific contexts.
     *
     * @param  array<string>  $createdFiles
     * @param  array<string>  $messages
     */
    private function createAdditionalRules(string $rulesDir, array &$createdFiles, array &$messages): void
    {
        // API Development Rules
        $apiRules = <<<'RULES'
# API Development Rules

When working on API endpoints:

## Response Format
All API responses should follow this structure:
```json
{
    "success": true,
    "data": { },
    "message": "Operation successful",
    "meta": {
        "timestamp": "2024-01-01T00:00:00Z",
        "request_id": "uuid"
    }
}
```

## Error Responses
```json
{
    "success": false,
    "error": {
        "code": "ERROR_CODE",
        "message": "Human readable message",
        "details": { }
    },
    "meta": { }
}
```

## HTTP Status Codes
- 200: Success (GET, PUT, PATCH)
- 201: Created (POST)
- 204: No Content (DELETE)
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 429: Too Many Requests
- 500: Server Error

## API Resources
Always use Laravel API Resources for response formatting.
Use Resource Collections for paginated results.

## Versioning
Prefix all API routes with version: /api/v1/
RULES;

        $this->writeFile($rulesDir.'/api-rules.md', $apiRules, $createdFiles);

        // Database Rules
        $dbRules = <<<'RULES'
# Database Development Rules

## Migrations
- Always make migrations reversible
- Use proper column types (unsignedInteger for foreign keys, etc.)
- Add appropriate indexes for frequently queried columns
- Use foreign key constraints with cascading deletes/updates

## Queries
- Always use parameter binding (never raw queries with user input)
- Use select() to limit columns when not all are needed
- Use chunk() for processing large datasets
- Avoid N+1 queries - use with() for eager loading
- Use explain() to analyze slow queries

## Models
- Always define $fillable or $guarded
- Define relationships with proper return types
- Use $casts for date, boolean, and JSON fields
- Define scopes for common query conditions
RULES;

        $this->writeFile($rulesDir.'/database-rules.md', $dbRules, $createdFiles);

        // Testing Rules
        $testRules = <<<'RULES'
# Testing Rules

## Test Structure
Use Pest PHP with describe/it syntax:
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

## What to Test
1. Happy path scenarios
2. Edge cases and boundary conditions
3. Error conditions and exceptions
4. Authorization checks
5. Business rule validation

## What NOT to Do
- Don't test framework code
- Don't write tests that always pass
- Don't ignore failing tests
- Don't test implementation details

## Coverage Requirements
- Minimum 80% code coverage
- All public methods must have tests
- Critical paths must have multiple test scenarios
RULES;

        $this->writeFile($rulesDir.'/testing-rules.md', $testRules, $createdFiles);

        $messages[] = 'Created additional rules: api-rules, database-rules, testing-rules';
    }
}
