<?php

declare(strict_types=1);

namespace LaraForge\AgentSupport\Agents;

use LaraForge\AgentSupport\AbstractAgentSupport;

/**
 * Windsurf IDE Agent Support
 *
 * Provides integration with Windsurf (Codeium's AI-native IDE), including:
 * - .windsurfrules configuration file
 * - Cascade AI context
 * - Project-specific guidelines
 */
final class WindsurfSupport extends AbstractAgentSupport
{
    public function identifier(): string
    {
        return 'windsurf';
    }

    public function name(): string
    {
        return 'Windsurf';
    }

    public function description(): string
    {
        return 'Integration with Windsurf IDE (Codeium), supporting .windsurfrules for Cascade AI assistance.';
    }

    public function specVersion(): string
    {
        return '2025.01';
    }

    public function isApplicable(string $projectPath): bool
    {
        // Windsurf is applicable to any project
        return true;
    }

    /**
     * @return array<string>
     */
    public function getRootFiles(): array
    {
        return ['.windsurfrules'];
    }

    /**
     * @return array<string, string>
     */
    public function getDirectoryStructure(): array
    {
        return [
            'context' => 'Additional context files for Cascade',
            'memories' => 'Project memories for AI persistence',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getCapabilities(): array
    {
        return [
            'rules' => true,
            'cascade_flows' => true,
            'memories' => true,
            'codebase_indexing' => true,
            'multi_file_editing' => true,
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

        // Create .windsurfrules in project root
        $rulesPath = $projectPath.'/.windsurfrules';
        $rulesContent = $this->generateMainConfig($fullContext);
        $this->writeFile($rulesPath, $rulesContent, $createdFiles);
        $messages[] = 'Created .windsurfrules for Windsurf Cascade AI';

        // Create agent directory structure
        $agentDir = $this->ensureAgentDirectory($projectPath);

        // Create context directory
        $contextDir = $agentDir.'/context';
        $this->filesystem->mkdir($contextDir);
        $this->createContextFiles($contextDir, $createdFiles, $messages);

        // Create memories directory
        $memoriesDir = $agentDir.'/memories';
        $this->filesystem->mkdir($memoriesDir);
        $this->createMemoriesFile($memoriesDir, $createdFiles, $messages);

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

        // Remove .windsurfrules
        $this->removeFile($projectPath.'/.windsurfrules', $removedFiles);

        // Remove agent directory
        $agentDir = $this->getAgentDirectory($projectPath);
        if ($this->filesystem->exists($agentDir)) {
            $this->filesystem->remove($agentDir);
            $removedFiles[] = $agentDir;
            $messages[] = 'Removed Windsurf agent directory';
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

        // Regenerate .windsurfrules
        $rulesPath = $projectPath.'/.windsurfrules';
        $rulesContent = $this->generateMainConfig($fullContext);
        $this->filesystem->dumpFile($rulesPath, $rulesContent);
        $updatedFiles[] = $rulesPath;
        $messages[] = 'Updated .windsurfrules with latest documentation references';

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
            $criteriaSection .= "\nAcceptance Criteria:\n";
            foreach ($context['criteriaFiles'] as $file) {
                $criteriaSection .= "- {$file}\n";
            }
        }

        return <<<RULES
# {$projectName} - Windsurf Rules
# {$projectDescription}
# Framework: {$framework}
# Generated by LaraForge

{$docsSection}{$criteriaSection}

## Project Context

This is a Laravel/PHP project using modern PHP 8.2+ features and strict typing.

Key technologies:
- Framework: Laravel 11+
- Testing: Pest PHP
- Static Analysis: PHPStan Level 8
- Code Style: Laravel Pint (PSR-12)

## Cascade Guidelines

When using Cascade for this project:

### Code Generation
- Always include `declare(strict_types=1);` in PHP files
- Use final classes by default
- Use readonly properties where data shouldn't change
- Add type declarations to all parameters and return types
- Follow PSR-12 coding standards

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

    public function doSomething(InputDTO \$input): OutputDTO
    {
        // Implementation
    }
}
```

### Architecture Patterns

Use these patterns consistently:
- **Actions**: Single-purpose operation classes
- **Services**: Business logic coordination
- **DTOs**: Immutable data transfer objects
- **Repositories**: Data access abstraction (when needed)
- **Events/Listeners**: Decoupled side effects

Directory structure:
```
app/
├── Actions/           # Single-purpose actions
├── Contracts/         # Interfaces
├── DTOs/              # Data Transfer Objects
├── Enums/             # PHP enums
├── Events/            # Domain events
├── Exceptions/        # Custom exceptions
├── Http/              # Controllers, Middleware, Requests
├── Jobs/              # Queue jobs
├── Listeners/         # Event listeners
├── Models/            # Eloquent models
├── Policies/          # Authorization
├── Services/          # Business logic
└── Support/           # Utilities
```

## Security Rules

Always follow these security practices:
- Validate ALL user inputs at system boundaries
- Use parameter binding for ALL database queries
- Never hardcode credentials or secrets
- Enable CSRF protection on forms
- Use \$fillable or \$guarded on all Eloquent models
- Never log sensitive data (passwords, tokens, PII)

## Testing Requirements

Write tests using Pest PHP:
```php
describe('ClassName', function () {
    it('does expected behavior', function () {
        // Arrange
        \$input = [...];

        // Act
        \$result = doSomething(\$input);

        // Assert
        expect(\$result)->toBe(...);
    });
});
```

Test coverage requirements:
- Minimum 80% overall coverage
- All public methods must have tests
- Include happy path, edge cases, and error scenarios

## Git Workflow

Branch naming convention:
- feature/ABC-123-description
- bugfix/ABC-456-description
- hotfix/ABC-789-description

Commit message format (Conventional Commits):
```
type(scope): description

[optional body]

[optional footer]
```

Types: feat, fix, docs, style, refactor, perf, test, build, ci, chore

## CLI Commands

```bash
# Run tests
./vendor/bin/pest

# Run with coverage
./vendor/bin/pest --coverage

# Static analysis
./vendor/bin/phpstan analyse

# Fix code style
./vendor/bin/pint

# LaraForge commands
./vendor/bin/laraforge next
./vendor/bin/laraforge generate <type>
./vendor/bin/laraforge criteria:validate
```

## AI Behavior Rules

DO:
- Read existing code before making changes
- Follow established patterns in the codebase
- Write tests alongside new code
- Keep changes focused and minimal
- Reference documentation for requirements

DON'T:
- Commit directly to main/master
- Remove or bypass tests
- Ignore static analysis errors
- Hardcode environment values
- Store secrets in code
- Refactor unrelated code during feature work
RULES;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSkillFormat(): array
    {
        return [
            'format' => 'rules',
            'location' => '.windsurfrules',
            'naming' => 'single-file',
            'structure' => [
                'description' => 'Windsurf uses rules file for Cascade AI context',
                'sections' => 'Context, Guidelines, Security, Testing, Commands',
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
        return 'https://docs.codeium.com/windsurf/memories';
    }

    public function priority(): int
    {
        return 75; // Good priority, newer AI IDE
    }

    /**
     * Create context files for Cascade.
     *
     * @param  array<string>  $createdFiles
     * @param  array<string>  $messages
     */
    private function createContextFiles(string $contextDir, array &$createdFiles, array &$messages): void
    {
        $architectureContext = <<<'MARKDOWN'
# Architecture Context

## Application Layers

### HTTP Layer
Controllers are thin and delegate to services/actions:
```php
final class OrderController
{
    public function store(StoreOrderRequest $request, CreateOrderAction $action): JsonResponse
    {
        $order = $action->execute(
            CreateOrderDTO::fromRequest($request)
        );

        return OrderResource::make($order)->response()->setStatusCode(201);
    }
}
```

### Business Layer
Services and Actions contain business logic:
```php
final readonly class CreateOrderAction
{
    public function __construct(
        private OrderRepository $orders,
        private InventoryService $inventory,
    ) {}

    public function execute(CreateOrderDTO $dto): Order
    {
        $this->inventory->reserve($dto->items);

        return $this->orders->create($dto);
    }
}
```

### Data Layer
Repositories abstract data access:
```php
interface OrderRepository
{
    public function create(CreateOrderDTO $dto): Order;
    public function findById(int $id): ?Order;
    public function findByUser(int $userId): Collection;
}
```

## Data Transfer Objects

DTOs are immutable:
```php
final readonly class CreateOrderDTO
{
    public function __construct(
        public int $userId,
        public array $items,
        public string $currency = 'USD',
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            userId: $request->user()->id,
            items: $request->validated('items'),
            currency: $request->validated('currency', 'USD'),
        );
    }
}
```

## Events and Listeners

Use events for side effects:
```php
// Dispatch event
OrderCreated::dispatch($order);

// Listener handles side effect
final class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        Mail::to($event->order->user)->send(
            new OrderConfirmationMail($event->order)
        );
    }
}
```
MARKDOWN;

        $this->writeFile($contextDir.'/architecture.md', $architectureContext, $createdFiles);

        $testingContext = <<<'MARKDOWN'
# Testing Context

## Test Organization

```
tests/
├── Unit/              # Isolated unit tests
│   ├── Actions/
│   ├── Services/
│   └── DTOs/
├── Feature/           # Integration tests
│   ├── Http/
│   └── Jobs/
├── Arch/              # Architecture tests
└── Pest.php           # Test configuration
```

## Unit Test Example

```php
describe(CalculateOrderTotalAction::class, function () {
    it('calculates total with multiple items', function () {
        $action = new CalculateOrderTotalAction();

        $total = $action->execute([
            ['price' => 1000, 'quantity' => 2],
            ['price' => 500, 'quantity' => 3],
        ]);

        expect($total)->toBe(3500);
    });

    it('returns zero for empty items', function () {
        $action = new CalculateOrderTotalAction();

        expect($action->execute([]))->toBe(0);
    });
});
```

## Feature Test Example

```php
describe('POST /api/orders', function () {
    it('creates order for authenticated user', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000]);

        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.total', 2000);

        expect(Order::count())->toBe(1);
    });

    it('requires authentication', function () {
        $this->postJson('/api/orders', ['items' => []])
            ->assertUnauthorized();
    });
});
```

## Architecture Test Example

```php
arch('services use strict types')
    ->expect('App\Services')
    ->toUseStrictTypes();

arch('controllers are final')
    ->expect('App\Http\Controllers')
    ->toBeFinal();

arch('DTOs are readonly')
    ->expect('App\DTOs')
    ->toBeReadonly();
```
MARKDOWN;

        $this->writeFile($contextDir.'/testing.md', $testingContext, $createdFiles);

        $messages[] = 'Created context files: architecture, testing';
    }

    /**
     * Create memories file for project state.
     *
     * @param  array<string>  $createdFiles
     * @param  array<string>  $messages
     */
    private function createMemoriesFile(string $memoriesDir, array &$createdFiles, array &$messages): void
    {
        $memoriesContent = <<<'MARKDOWN'
# Project Memories

This file tracks important decisions and patterns for Windsurf's Cascade AI.

## Established Patterns

### Authentication
- Uses Laravel Sanctum for API authentication
- Session-based auth for web routes
- Token refresh handled automatically

### Validation
- Always use Form Requests for validation
- Custom validation rules in app/Rules/

### Error Handling
- Custom exception handler for API errors
- Structured error responses with codes

## Recent Decisions

> Add important architectural decisions here as they are made.

## Known Issues

> Track ongoing issues that Cascade should be aware of.

## Code Review Notes

> Add patterns or anti-patterns discovered during code review.

---

*Update this file as the project evolves to maintain context.*
MARKDOWN;

        $this->writeFile($memoriesDir.'/project-memories.md', $memoriesContent, $createdFiles);
        $messages[] = 'Created project memories file';
    }
}
