<?php

declare(strict_types=1);

namespace LaraForge\AgentSupport\Agents;

use LaraForge\AgentSupport\AbstractAgentSupport;

/**
 * JetBrains IDE Agent Support
 *
 * Provides integration with JetBrains IDEs (PHPStorm, IntelliJ IDEA, WebStorm),
 * supporting JetBrains AI and the .idea directory configuration.
 */
final class JetBrainsSupport extends AbstractAgentSupport
{
    public function identifier(): string
    {
        return 'jetbrains';
    }

    public function name(): string
    {
        return 'JetBrains IDEs';
    }

    public function description(): string
    {
        return 'Integration with JetBrains IDEs (PHPStorm, IntelliJ IDEA), supporting AI context files and project configuration.';
    }

    public function specVersion(): string
    {
        return '2025.01';
    }

    public function isApplicable(string $projectPath): bool
    {
        // Check if .idea directory exists (JetBrains project)
        return $this->filesystem->exists($projectPath.'/.idea');
    }

    /**
     * @return array<string>
     */
    public function getRootFiles(): array
    {
        return ['.jb-ai-context.md'];
    }

    /**
     * @return array<string, string>
     */
    public function getDirectoryStructure(): array
    {
        return [
            'inspections' => 'Custom inspection profiles',
            'templates' => 'Live template definitions',
            'context' => 'AI context files',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getCapabilities(): array
    {
        return [
            'ai_context' => true,
            'inspections' => true,
            'live_templates' => true,
            'code_style' => true,
            'run_configurations' => true,
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

        // Create .jb-ai-context.md in project root
        $contextPath = $projectPath.'/.jb-ai-context.md';
        $contextContent = $this->generateMainConfig($fullContext);
        $this->writeFile($contextPath, $contextContent, $createdFiles);
        $messages[] = 'Created .jb-ai-context.md for JetBrains AI';

        // Create agent directory structure
        $agentDir = $this->ensureAgentDirectory($projectPath);

        // Create inspections directory
        $inspectionsDir = $agentDir.'/inspections';
        $this->filesystem->mkdir($inspectionsDir);
        $this->createInspectionProfile($inspectionsDir, $createdFiles, $messages);

        // Create templates directory
        $templatesDir = $agentDir.'/templates';
        $this->filesystem->mkdir($templatesDir);
        $this->createLiveTemplates($templatesDir, $createdFiles, $messages);

        // Create context directory for additional AI context
        $contextDir = $agentDir.'/context';
        $this->filesystem->mkdir($contextDir);
        $this->createContextFiles($contextDir, $createdFiles, $messages);

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

        // Remove .jb-ai-context.md
        $this->removeFile($projectPath.'/.jb-ai-context.md', $removedFiles);

        // Remove agent directory
        $agentDir = $this->getAgentDirectory($projectPath);
        if ($this->filesystem->exists($agentDir)) {
            $this->filesystem->remove($agentDir);
            $removedFiles[] = $agentDir;
            $messages[] = 'Removed JetBrains agent directory';
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

        // Regenerate .jb-ai-context.md
        $contextPath = $projectPath.'/.jb-ai-context.md';
        $contextContent = $this->generateMainConfig($fullContext);
        $this->filesystem->dumpFile($contextPath, $contextContent);
        $updatedFiles[] = $contextPath;
        $messages[] = 'Updated .jb-ai-context.md with latest documentation references';

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
                $criteriaSection .= "- {$file}\n";
            }
        }

        return <<<MARKDOWN
# {$projectName} - JetBrains AI Context

{$projectDescription}

> Generated by LaraForge - Last synced: {$generatedAt}

## Project Overview

**Framework:** {$framework}
**Language:** PHP 8.2+
**Testing:** Pest PHP
**Static Analysis:** PHPStan Level 8

## Documentation

{$docReferences}
{$criteriaSection}

## Code Standards

### PHP Configuration
- Strict types enabled in all files
- Type declarations for all parameters and return types
- Final classes by default
- Readonly properties where applicable

### Code Style
- PSR-12 coding standards
- Laravel Pint for formatting
- Maximum line length: 120 characters

### Class Template
```php
<?php

declare(strict_types=1);

namespace App\Services;

final readonly class ExampleService
{
    public function __construct(
        private DependencyInterface \$dependency,
    ) {}

    public function execute(InputDTO \$input): OutputDTO
    {
        // Implementation
    }
}
```

## Architecture

### Directory Structure
```
app/
├── Actions/       # Single-purpose actions
├── Contracts/     # Interfaces
├── DTOs/          # Data Transfer Objects
├── Enums/         # PHP enums
├── Events/        # Domain events
├── Exceptions/    # Custom exceptions
├── Http/          # Web layer
├── Jobs/          # Queue jobs
├── Listeners/     # Event listeners
├── Models/        # Eloquent models
├── Policies/      # Authorization
├── Services/      # Business logic
└── Support/       # Utilities
```

### Design Patterns
- Repository Pattern for data access
- Action Classes for operations
- Service Classes for business logic
- DTOs for data transfer
- Events/Listeners for side effects

## Testing

### Framework
Pest PHP with describe/it syntax

### Structure
```php
describe('ClassName', function () {
    it('does something', function () {
        // Arrange
        \$input = [...];

        // Act
        \$result = action(\$input);

        // Assert
        expect(\$result)->toBe(...);
    });
});
```

### Coverage
- Minimum 80% code coverage
- Unit tests for all services
- Feature tests for HTTP endpoints
- Architecture tests for structure

## Commands

```bash
# Testing
./vendor/bin/pest
./vendor/bin/pest --coverage

# Static Analysis
./vendor/bin/phpstan analyse

# Code Style
./vendor/bin/pint
./vendor/bin/pint --test

# LaraForge
./vendor/bin/laraforge next
./vendor/bin/laraforge generate <type>
```

## Security Guidelines

- Validate all user inputs
- Use parameter binding for queries
- Never hardcode credentials
- Enable CSRF protection
- Use \$fillable on models
- Log security events

## AI Instructions

When working in this codebase:

1. **Follow existing patterns** - Match the style of surrounding code
2. **Use strict types** - Always declare strict types
3. **Write tests** - Add tests for new functionality
4. **Keep changes minimal** - Don't refactor unrelated code
5. **Check documentation** - Reference PRD/FRD for requirements

---

*Managed by LaraForge*
MARKDOWN;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSkillFormat(): array
    {
        return [
            'format' => 'markdown',
            'location' => '.jb-ai-context.md',
            'naming' => 'single-file',
            'structure' => [
                'description' => 'JetBrains AI uses a context file for project understanding',
                'sections' => 'Overview, Standards, Architecture, Testing, Commands',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $skill
     */
    public function formatSkill(array $skill): string
    {
        $name = $skill['name'] ?? 'Unnamed Section';
        $content = $skill['content'] ?? '';

        return "## {$name}\n\n{$content}\n";
    }

    public function getDocumentationUrl(): string
    {
        return 'https://www.jetbrains.com/help/phpstorm/ai-assistant.html';
    }

    public function priority(): int
    {
        return 70; // Good priority for PHP development
    }

    /**
     * Create inspection profile.
     *
     * @param  array<string>  $createdFiles
     * @param  array<string>  $messages
     */
    private function createInspectionProfile(string $inspectionsDir, array &$createdFiles, array &$messages): void
    {
        $inspectionXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<component name="InspectionProjectProfileManager">
  <profile version="1.0">
    <option name="myName" value="LaraForge" />
    <inspection_tool class="PhpMissingStrictTypesDeclarationInspection" enabled="true" level="WARNING" enabled_by_default="true" />
    <inspection_tool class="PhpMissingReturnTypeInspection" enabled="true" level="WARNING" enabled_by_default="true" />
    <inspection_tool class="PhpMissingParamTypeInspection" enabled="true" level="WARNING" enabled_by_default="true" />
    <inspection_tool class="PhpMissingFieldTypeInspection" enabled="true" level="WARNING" enabled_by_default="true" />
    <inspection_tool class="PhpUnusedLocalVariableInspection" enabled="true" level="WARNING" enabled_by_default="true" />
    <inspection_tool class="PhpUnusedPrivateMethodInspection" enabled="true" level="WARNING" enabled_by_default="true" />
    <inspection_tool class="PhpUnusedPrivateFieldInspection" enabled="true" level="WARNING" enabled_by_default="true" />
    <inspection_tool class="SqlNoDataSourceInspection" enabled="false" level="WARNING" enabled_by_default="false" />
    <inspection_tool class="SqlResolveInspection" enabled="false" level="ERROR" enabled_by_default="false" />
  </profile>
</component>
XML;

        $this->writeFile($inspectionsDir.'/LaraForge.xml', $inspectionXml, $createdFiles);
        $messages[] = 'Created LaraForge inspection profile';
    }

    /**
     * Create live templates.
     *
     * @param  array<string>  $createdFiles
     * @param  array<string>  $messages
     */
    private function createLiveTemplates(string $templatesDir, array &$createdFiles, array &$messages): void
    {
        $templatesXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<templateSet group="LaraForge">
  <template name="lfclass" value="&lt;?php&#10;&#10;declare(strict_types=1);&#10;&#10;namespace $NAMESPACE$;&#10;&#10;final class $NAME$&#10;{&#10;    public function __construct(&#10;        $PARAMS$&#10;    ) {}&#10;&#10;    $END$&#10;}" description="LaraForge final class" toReformat="true" toShortenFQNames="true">
    <variable name="NAMESPACE" expression="phpNamespace()" defaultValue="" alwaysStopAt="true" />
    <variable name="NAME" expression="phpClassName()" defaultValue="" alwaysStopAt="true" />
    <variable name="PARAMS" expression="" defaultValue="" alwaysStopAt="true" />
    <context>
      <option name="PHP" value="true" />
    </context>
  </template>
  <template name="lfdto" value="&lt;?php&#10;&#10;declare(strict_types=1);&#10;&#10;namespace $NAMESPACE$;&#10;&#10;final readonly class $NAME$&#10;{&#10;    public function __construct(&#10;        $PARAMS$&#10;    ) {}&#10;}" description="LaraForge DTO class" toReformat="true" toShortenFQNames="true">
    <variable name="NAMESPACE" expression="phpNamespace()" defaultValue="" alwaysStopAt="true" />
    <variable name="NAME" expression="phpClassName()" defaultValue="" alwaysStopAt="true" />
    <variable name="PARAMS" expression="" defaultValue="" alwaysStopAt="true" />
    <context>
      <option name="PHP" value="true" />
    </context>
  </template>
  <template name="lftest" value="describe('$CLASS$', function () {&#10;    describe('$METHOD$', function () {&#10;        it('$DESCRIPTION$', function () {&#10;            // Arrange&#10;            $ARRANGE$&#10;&#10;            // Act&#10;            $ACT$&#10;&#10;            // Assert&#10;            expect($RESULT$)->$ASSERTION$;&#10;        });&#10;    });&#10;});" description="LaraForge Pest test" toReformat="true" toShortenFQNames="true">
    <variable name="CLASS" expression="" defaultValue="ClassName" alwaysStopAt="true" />
    <variable name="METHOD" expression="" defaultValue="methodName" alwaysStopAt="true" />
    <variable name="DESCRIPTION" expression="" defaultValue="does something" alwaysStopAt="true" />
    <variable name="ARRANGE" expression="" defaultValue="" alwaysStopAt="true" />
    <variable name="ACT" expression="" defaultValue="" alwaysStopAt="true" />
    <variable name="RESULT" expression="" defaultValue="result" alwaysStopAt="true" />
    <variable name="ASSERTION" expression="" defaultValue="toBeTrue()" alwaysStopAt="true" />
    <context>
      <option name="PHP" value="true" />
    </context>
  </template>
</templateSet>
XML;

        $this->writeFile($templatesDir.'/LaraForge.xml', $templatesXml, $createdFiles);
        $messages[] = 'Created LaraForge live templates';
    }

    /**
     * Create additional context files.
     *
     * @param  array<string>  $createdFiles
     * @param  array<string>  $messages
     */
    private function createContextFiles(string $contextDir, array &$createdFiles, array &$messages): void
    {
        $laravelContext = <<<'MARKDOWN'
# Laravel Development Context

## Key Patterns

### Service Classes
Services contain business logic and are injected via constructor:
```php
final readonly class OrderService
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentGateway $payments,
    ) {}
}
```

### Action Classes
Single-purpose actions for specific operations:
```php
final readonly class CreateOrderAction
{
    public function execute(CreateOrderDTO $dto): Order
    {
        // Single responsibility
    }
}
```

### Form Requests
Always use Form Requests for validation:
```php
final class StoreOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

### API Resources
Use Resources for API responses:
```php
final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total' => $this->total_cents / 100,
            'status' => $this->status->value,
        ];
    }
}
```

## Testing Patterns

### Feature Tests
```php
it('creates an order', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

    $response->assertCreated();
    expect(Order::count())->toBe(1);
});
```

### Unit Tests
```php
it('calculates order total', function () {
    $order = new Order(items: [
        new OrderItem(price: 1000, quantity: 2),
        new OrderItem(price: 500, quantity: 1),
    ]);

    expect($order->total())->toBe(2500);
});
```
MARKDOWN;

        $this->writeFile($contextDir.'/laravel-patterns.md', $laravelContext, $createdFiles);
        $messages[] = 'Created Laravel patterns context file';
    }
}
