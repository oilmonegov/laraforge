# LaraForge

AI-first project scaffolding framework with Claude Code, Cursor, and VS Code support.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oilmonegov/laraforge.svg?style=flat-square)](https://packagist.org/packages/oilmonegov/laraforge)
[![PHP Version](https://img.shields.io/packagist/php-v/oilmonegov/laraforge.svg?style=flat-square)](https://packagist.org/packages/oilmonegov/laraforge)
[![Tests](https://img.shields.io/github/actions/workflow/status/oilmonegov/laraforge/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/oilmonegov/laraforge/actions)
[![License](https://img.shields.io/packagist/l/oilmonegov/laraforge.svg?style=flat-square)](https://packagist.org/packages/oilmonegov/laraforge)

## Overview

LaraForge is an AI-first project scaffolding framework designed for both new and existing PHP projects. It provides intelligent architecture recommendations, standardized patterns, and seamless integration with AI coding assistants.

### Key Features

- **Multi-Framework Support** — Works with Laravel, Symfony, and generic PHP projects
- **Architecture-Aware** — Scale-based recommendations for sync/async, caching, queues
- **Interactive Setup** — Smart questions at project initialization, autonomous after guidelines established
- **Standardized API Responses** — Consistent, secure response formatting
- **Comprehensive Logging** — AAA (Authentication, Authorization, Accounting) audit logging
- **Configuration Protection** — Safeguards for critical files with permission systems
- **External Documentation Sync** — Fetches from Laravel, PHP, and frontend framework docs
- **Security Hooks** — OWASP Top 10 validation during development

## Installation

```bash
composer require oilmonegov/laraforge --dev
```

For global installation:

```bash
composer global require oilmonegov/laraforge
```

## Quick Start

### For New Projects

```bash
# Initialize with interactive setup
laraforge init

# Answer questions about:
# - Project scale (users, records, traffic)
# - Technology stack preferences
# - UI framework choices
# - Storage requirements
```

### For Existing Projects

```bash
# Analyze existing project and generate recommendations
laraforge init --existing

# LaraForge will detect:
# - Framework (Laravel, Symfony, etc.)
# - Existing patterns and conventions
# - Technology stack from composer.json
```

## Architecture

### Project Scale

LaraForge uses project scale to make intelligent architecture decisions:

| Tier | Users | Records | Mode |
|------|-------|---------|------|
| Prototype | < 100 | 10K | Simple |
| Small | 100-1K | 100K | Simple |
| Medium | 1K-100K | 10M | Balanced |
| Large | 100K-1M | 100M | Scalable |
| Massive | 1M+ | 1B+ | Scalable |

```php
use LaraForge\Architecture\ProjectScale;
use LaraForge\Architecture\ArchitectureAdvisor;

// Define your project scale
$scale = ProjectScale::fromTier(ProjectScale::TIER_MEDIUM);

// Get architecture recommendations
$advisor = new ArchitectureAdvisor($scale);

// Determine sync vs async for operations
$recommendation = $advisor->recommendExecutionMode('notification');
// Returns: ['mode' => 'async', 'reason' => '...', 'implementation' => [...]]

// Get patterns for features
$patterns = $advisor->recommendPatterns('reporting');
// Includes caching strategy, async recommendations, chunking advice
```

### Architecture Modes

- **Simple** — Monolith, synchronous, minimal infrastructure
- **Balanced** — Monolith with queues, Redis caching
- **Scalable** — Microservices-ready, async-first, horizontal scaling

## Core Components

### API Responses

Standardized, secure API response formatting:

```php
use LaraForge\Api\ApiResponse;

// Success responses
return ApiResponse::success('User created', ['user' => $user]);
return ApiResponse::created('Resource created', $data);
return ApiResponse::paginated($items, $pagination);

// Error responses
return ApiResponse::validationError('Invalid input', $errors);
return ApiResponse::notFound('User not found');
return ApiResponse::unauthorized();
return ApiResponse::serverError('Something went wrong', 'ERR_12345');

// Automatic sensitive field stripping
// Fields like 'password', 'token', 'api_key' are automatically removed
```

### Exception Handling

Safe exception handling for APIs:

```php
use LaraForge\Api\ExceptionHandler;

$handler = new ExceptionHandler(
    debug: config('app.debug'),
    logger: fn($context) => Log::error('API Exception', $context)
);

// In your exception handler
$response = $handler->handle($exception);
return response()->json($response->toArray(), $response->getHttpCode());
```

### Audit Logging (AAA)

Comprehensive logging for security and compliance:

```php
use LaraForge\Logging\AuditLogger;

$logger = new AuditLogger('/path/to/logs', 'medium');

// Authentication events
$logger->logAuth('user.login', $userId, ['ip' => $request->ip()]);

// Authorization events
$logger->logAuthz('permission.check', $userId, 'posts', 'delete', $allowed);

// Data access events
$logger->logDataAccess('record.viewed', 'User', $userId, $actorId, 'read');

// API requests
$logger->logApi('POST', '/api/users', 201, $durationMs, $userId);

// Security events
$logger->logSecurity('suspicious.activity', ['reason' => 'Multiple failed logins']);
```

For Laravel projects, we recommend [Spatie Activity Log](https://github.com/spatie/laravel-activitylog):

```php
// Get package recommendations
AuditLogger::getRecommendedPackages();
```

### Configuration Protection

Protect critical files from accidental modification:

```php
use LaraForge\Config\ConfigProtection;

$protection = new ConfigProtection('/path/to/project');

// Check if file is protected
$status = $protection->checkProtection('tests/Architecture/ArchTest.php');
// Returns: ['protected' => true, 'level' => 'critical', 'requires_separate_pr' => true]

// Request permission to modify
$permission = $protection->requestPermission(
    filePath: 'config/app.php',
    justification: 'Updating timezone for production deployment',
    requestedBy: 'developer@example.com'
);

// Protected file categories:
// - Architecture tests (critical - requires separate PR)
// - Security configs (protected)
// - CI/CD configs (protected)
// - Core app configs (protected)
// - Environment files (warn)
```

Install the Git hook to enforce protection:

```bash
# The hook warns when protected files are modified alongside other files
cat > .git/hooks/pre-commit << 'EOF'
$(laraforge git-hook pre-commit)
EOF
chmod +x .git/hooks/pre-commit
```

### Design System

Manage UI components, brand guidelines, and storage:

```php
use LaraForge\DesignSystem\DesignSystem;

$design = DesignSystem::forProject('/path/to/project');

// Brand guidelines
$brand = $design->getBrand();
$colors = $brand->getColors(); // Primary, secondary, semantic colors
$typography = $brand->getTypography(); // Font families, sizes, weights

// Component library
$components = $design->getComponents();
$tableVariants = $components->getVariants('table');
// Returns: simple, sortable, searchable, paginated, selectable, advanced

// Storage configuration (S3-compatible)
$storage = $design->getStorage();
$config = $storage->getConfig('images');
// Returns CDN URL, bucket, visibility settings

// Service resilience patterns
$resilience = $design->getResilience();
$circuitBreaker = $resilience->getCircuitBreakerPattern('payment-gateway');
$retryPattern = $resilience->getRetryPattern();
```

### Documentation Sync

Fetch and cache external documentation:

```php
use LaraForge\Documentation\DocumentationSync;

$docs = DocumentationSync::fromPath('/path/to/project');

// Fetch Laravel documentation
$validation = $docs->fetch('laravel', 'validation', '11.x');

// Fetch package info from Packagist
$packageInfo = $docs->fetchPackageInfo('spatie', 'laravel-activitylog');

// Fetch latest release from GitHub
$release = $docs->fetchLatestRelease('laravel', 'framework');

// Check cache status
$status = $docs->getCacheStatus();
```

### Security Hooks

OWASP Top 10 validation during development:

```php
use LaraForge\Hooks\SecurityHook;
use LaraForge\Project\ProjectContext;

$hook = new SecurityHook();

// Scan code for security issues
$issues = $hook->scan($codeContent, 'app/Http/Controllers/UserController.php');

// Returns issues like:
// - SQL injection vulnerabilities
// - XSS risks
// - CSRF missing
// - Mass assignment vulnerabilities
// - Command injection risks
```

## Skills System

Skills are reusable capabilities for AI assistants:

```php
use LaraForge\Skills\SkillRegistry;

$registry = new SkillRegistry($laraforge);

// Document skills
$registry->get('create-prd');      // Create Product Requirements Document
$registry->get('create-frd');      // Create Feature Requirements Document
$registry->get('create-pseudocode'); // Create implementation pseudocode

// Generator skills
$registry->get('api-resource');    // Generate API Resource classes
$registry->get('feature-test');    // Generate feature tests
$registry->get('policy');          // Generate authorization policies
$registry->get('manager');         // Generate manager pattern classes

// Git skills
$registry->get('branch');          // Create feature branches
$registry->get('commit');          // Smart commits
$registry->get('worktree');        // Manage git worktrees
```

## Workflows

Structured workflows for common development tasks:

```php
use LaraForge\Workflows\FeatureWorkflow;

$workflow = new FeatureWorkflow($laraforge);

// Get workflow steps
$steps = $workflow->steps();
// 1. Requirements (PRD)
// 2. Design (FRD)
// 3. Test Contract
// 4. Branch
// 5. Implement
// 6. Verify
// 7. Review
// 8. Merge

// Execute current step
$result = $workflow->getCurrentStep()->execute($context);

// Track progress
$progress = $workflow->progress(); // 0-100%
```

## Interactive Setup

Smart question handling during project setup:

```php
use LaraForge\Project\InteractionContext;

$context = new InteractionContext();

// Check if we should ask about something
if ($context->shouldAsk('database', 'Which database?')) {
    // Ask user
    $answer = $this->ask('Which database would you like to use?');
    $context->establish('database', 'primary_database', $answer);
}

// Once established, won't ask again
$context->getEstablished('database', 'primary_database'); // Returns previous answer

// Check completeness
$score = $context->getCompletenessScore(); // 0.0 - 1.0

// Switch modes based on completeness
if ($score > 0.8) {
    $context->setMode(InteractionContext::MODE_AUTONOMOUS);
}
```

## Multi-Framework Support

### Laravel

```php
use LaraForge\Frameworks\LaravelAdapter;

$adapter = new LaravelAdapter();
$adapter->isApplicable('/path/to/project'); // Checks for laravel/framework

// Laravel-specific features
$adapter->getArtisanCommands();
$adapter->getMiddlewarePatterns();
$adapter->getEloquentPatterns();
```

### Symfony

```php
use LaraForge\Frameworks\SymfonyAdapter;

$adapter = new SymfonyAdapter();
// Symfony-specific features
```

### Generic PHP

```php
use LaraForge\Frameworks\GenericPhpAdapter;

$adapter = new GenericPhpAdapter();
// Works with any PHP project
```

## Configuration

### Project Configuration

Create `laraforge.yaml` or `.laraforge/config.yaml`:

```yaml
project:
  name: my-project
  description: My awesome project

scale:
  tier: medium
  mode: balanced
  expected_users: 50000
  expected_records: 5000000

framework: laravel

ai_tools:
  - claude
  - cursor

features:
  skills: true
  commands: true
  agents: true
  workflows: true
  security_hooks: true

storage:
  default: s3
  cdn: cloudfront

design:
  ui_framework: tailwind
  component_library: shadcn
```

### Override System

The `.laraforge/` directory allows project-level customization:

```
.laraforge/
├── config.yaml           # Override default configuration
├── templates/            # Override any template
│   └── CLAUDE.md        # Custom CLAUDE.md template
├── stubs/               # Override stubs
│   └── action.stub
├── protections.json     # Custom file protections
├── permissions.json     # Granted permissions
└── cache/               # Documentation cache
    └── documentation_cache.json
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `laraforge init` | Initialize LaraForge in your project |
| `laraforge init --existing` | Initialize in existing project |
| `laraforge status` | Show project status and progress |
| `laraforge feature:start` | Start a new feature workflow |
| `laraforge skill:run [name]` | Execute a skill |
| `laraforge generators` | List available generators |
| `laraforge generate [name]` | Run a generator |
| `laraforge worktree:create` | Create a git worktree for parallel work |

## Requirements

- PHP 8.4+
- Composer 2.0+

### Recommended Packages

For Laravel projects:

```bash
# Activity logging
composer require spatie/laravel-activitylog

# Backups
composer require spatie/laravel-backup

# Development debugging
composer require spatie/laravel-ray --dev
```

## Testing

```bash
# Run tests
./vendor/bin/pest

# Run with coverage
./vendor/bin/pest --coverage

# Run specific test suite
./vendor/bin/pest --filter=Architecture
./vendor/bin/pest --filter=Unit
./vendor/bin/pest --filter=Stress
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/oilmonegov/laraforge.git
cd laraforge
composer install
./vendor/bin/pest
```

### Code Quality

```bash
# Code style
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# All checks
composer check
```

## Security

- Security issues should be reported via email to security@oilmonegov.com
- Do not open public issues for security vulnerabilities
- See [SECURITY.md](SECURITY.md) for our security policy

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Credits

- Built with Claude Code assistance
- Inspired by Laravel's elegant patterns
- Uses Symfony Console for CLI
