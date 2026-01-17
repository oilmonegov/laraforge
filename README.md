# LaraForge

AI-first project scaffolding framework with Claude Code, Cursor, and VS Code support.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oilmonegov/laraforge.svg?style=flat-square)](https://packagist.org/packages/oilmonegov/laraforge)
[![PHP Version](https://img.shields.io/packagist/php-v/oilmonegov/laraforge.svg?style=flat-square)](https://packagist.org/packages/oilmonegov/laraforge)
[![License](https://img.shields.io/packagist/l/oilmonegov/laraforge.svg?style=flat-square)](https://packagist.org/packages/oilmonegov/laraforge)

## Overview

LaraForge helps you scaffold AI-ready projects with:

- 🤖 **CLAUDE.md** — Project context for Claude Code
- ⚡ **Cursor Rules** — Configuration for Cursor IDE
- 📝 **Skills** — Knowledge base for AI assistants
- 🔧 **Commands** — Custom slash commands
- 🎭 **Agents** — Specialized sub-agents
- 📦 **Override System** — Project-level customization via `.laraforge/`

## Installation

```bash
composer global require oilmonegov/laraforge
```

Or install locally in your project:

```bash
composer require oilmonegov/laraforge --dev
```

## Quick Start

```bash
# Initialize LaraForge in your project
laraforge init

# List available generators
laraforge generators

# Generate files
laraforge generate [generator-name]
```

## Framework Adapters

LaraForge is framework-agnostic. Install adapters for framework-specific features:

### Laravel

```bash
composer require oilmonegov/laraforge-laravel --dev
```

Provides:
- Laravel-specific generators (Actions, Queries, DTOs, MCP)
- Artisan command integration
- Starter kit scaffolding via `laravel/installer`

### More Coming Soon

- Symfony adapter
- Slim adapter
- Vanilla PHP adapter

## Configuration

### Project Configuration

Create a `laraforge.yaml` or `.laraforge/config.yaml` in your project root:

```yaml
project:
  name: my-project
  description: My awesome project

framework: laravel

ai_tools:
  - claude
  - cursor

features:
  skills: true
  commands: true
  agents: true
  quality: true
```

### Override System

The `.laraforge/` directory allows project-level customization:

```
.laraforge/
├── config.yaml       # Override default configuration
├── templates/        # Override any template
│   └── CLAUDE.md    # Custom CLAUDE.md template
├── stubs/           # Override stubs
│   └── action.stub
└── plugins/         # Local plugins
    └── my-plugin.php
```

**Resolution order:**
1. `.laraforge/` (project-level overrides)
2. Framework adapter templates
3. Core defaults

## Creating Plugins

```php
<?php

namespace MyVendor\MyPlugin;

use LaraForge\Plugins\Plugin;
use LaraForge\Contracts\LaraForgeInterface;

class MyPlugin extends Plugin
{
    public function identifier(): string
    {
        return 'my-plugin';
    }

    public function name(): string
    {
        return 'My Plugin';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function description(): string
    {
        return 'Adds custom functionality to LaraForge';
    }

    public function generators(): array
    {
        return [
            'my-generator' => MyGenerator::class,
        ];
    }

    public function templatesPath(): ?string
    {
        return __DIR__ . '/../resources/templates';
    }
}
```

## Creating Adapters

```php
<?php

namespace MyVendor\MyAdapter;

use LaraForge\Adapters\Adapter;

class MyFrameworkAdapter extends Adapter
{
    public function identifier(): string
    {
        return 'my-framework';
    }

    public function name(): string
    {
        return 'My Framework';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function isApplicable(string $projectPath): bool
    {
        // Check if this framework is in use
        return file_exists($projectPath . '/my-framework.json');
    }

    public function priority(): int
    {
        return 20; // Higher = checked first
    }

    public function templatesPath(): string
    {
        return __DIR__ . '/../resources/templates';
    }

    public function stubsPath(): string
    {
        return __DIR__ . '/../resources/stubs';
    }

    public function generators(): array
    {
        return [
            'controller' => ControllerGenerator::class,
            'model' => ModelGenerator::class,
        ];
    }
}
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `laraforge init` | Initialize LaraForge in your project |
| `laraforge generators` | List available generators |
| `laraforge generate [name]` | Run a generator |
| `laraforge version` | Display version information |

## Requirements

- PHP 8.4+
- Composer

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please email security@oilmonegov.com.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
