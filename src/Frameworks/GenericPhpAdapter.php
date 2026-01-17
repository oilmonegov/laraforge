<?php

declare(strict_types=1);

namespace LaraForge\Frameworks;

use LaraForge\Core\Contracts\FrameworkAdapterInterface;
use LaraForge\Core\FrameworkDetector;

/**
 * Generic PHP Framework Adapter
 *
 * Provides code generation and best practices for generic PHP projects
 * that don't use a specific framework.
 */
final class GenericPhpAdapter implements FrameworkAdapterInterface
{
    public function __construct(
        private readonly FrameworkDetector $detector,
    ) {}

    public function identifier(): string
    {
        return 'generic';
    }

    public function name(): string
    {
        return 'Generic PHP';
    }

    public function version(): ?string
    {
        return null;
    }

    public function isApplicable(): bool
    {
        return $this->detector->isGeneric();
    }

    public function getDirectoryStructure(): array
    {
        return [
            'source' => 'src',
            'tests' => 'tests',
            'config' => 'config',
            'public' => 'public',
            'resources' => 'resources',
            'storage' => 'storage',
            'vendor' => 'vendor',
        ];
    }

    public function getStubTemplates(): array
    {
        return [
            'class' => 'stubs/generic/class.stub',
            'interface' => 'stubs/generic/interface.stub',
            'trait' => 'stubs/generic/trait.stub',
            'enum' => 'stubs/generic/enum.stub',
            'test' => 'stubs/generic/test.stub',
            'config' => 'stubs/generic/config.stub',
        ];
    }

    public function getSecurityRules(): array
    {
        return [
            'sql_injection' => [
                'pattern' => '/\bmysqli?_query\s*\([^)]*\$/i',
                'message' => 'Potential SQL injection with mysqli. Use prepared statements.',
                'severity' => 'critical',
            ],
            'file_inclusion' => [
                'pattern' => '/\b(?:include|require)(?:_once)?\s*\([^)]*\$/i',
                'message' => 'Dynamic file inclusion is dangerous. Validate paths.',
                'severity' => 'critical',
            ],
            'command_execution' => [
                'pattern' => '/\b(?:exec|shell_exec|system|passthru|popen)\s*\([^)]*\$/i',
                'message' => 'Command execution with user input. Use escapeshellarg().',
                'severity' => 'critical',
            ],
            'xss' => [
                'pattern' => '/echo\s+\$_(?:GET|POST|REQUEST)/i',
                'message' => 'Echoing user input directly. Use htmlspecialchars().',
                'severity' => 'high',
            ],
            'session_fixation' => [
                'pattern' => '/session_start\s*\(\)(?!.*session_regenerate_id)/is',
                'message' => 'Regenerate session ID after authentication.',
                'severity' => 'high',
            ],
            'weak_crypto' => [
                'pattern' => '/\b(?:md5|sha1)\s*\([^)]*password/i',
                'message' => 'Use password_hash() for passwords, not MD5/SHA1.',
                'severity' => 'critical',
            ],
        ];
    }

    public function getDocumentationUrls(): array
    {
        return [
            'php' => 'https://www.php.net/manual/en/',
            'psr' => 'https://www.php-fig.org/psr/',
            'composer' => 'https://getcomposer.org/doc/',
            'phpunit' => 'https://docs.phpunit.de/',
            'pest' => 'https://pestphp.com/docs/',
            'phpstan' => 'https://phpstan.org/user-guide/',
        ];
    }

    public function getCodingStandards(): array
    {
        return [
            'psr' => [
                'psr-1' => 'Basic Coding Standard',
                'psr-4' => 'Autoloading Standard',
                'psr-12' => 'Extended Coding Style',
            ],
            'php' => [
                'strict_types' => true,
                'declare_types' => true,
                'constructor_promotion' => true,
                'readonly_properties' => true,
                'named_arguments' => true,
            ],
            'naming' => [
                'classes' => 'PascalCase',
                'methods' => 'camelCase',
                'properties' => 'camelCase',
                'constants' => 'SCREAMING_SNAKE_CASE',
                'files' => 'PascalCase matching class name',
            ],
        ];
    }

    public function getTestingConventions(): array
    {
        return [
            'framework' => 'pest',
            'directory' => 'tests',
            'naming' => [
                'test_files' => '*Test.php',
                'test_methods' => 'it_* or test_*',
            ],
            'structure' => [
                'unit' => 'tests/Unit',
                'integration' => 'tests/Integration',
                'feature' => 'tests/Feature',
            ],
        ];
    }

    public function resolvePath(string $component, string $name): string
    {
        $structure = $this->getDirectoryStructure();
        $basePath = $structure['source'] ?? 'src';

        return match ($component) {
            'class' => "{$basePath}/{$name}.php",
            'interface' => "{$basePath}/Contracts/{$name}.php",
            'trait' => "{$basePath}/Traits/{$name}.php",
            'test' => "tests/{$name}Test.php",
            'config' => "config/{$name}.php",
            default => "{$basePath}/{$name}.php",
        };
    }

    public function getAiContext(): array
    {
        return [
            'framework' => 'Generic PHP',
            'patterns' => [
                'Use PSR-4 autoloading',
                'Follow PSR-12 coding style',
                'Use strict types in all files',
                'Prefer composition over inheritance',
                'Use dependency injection',
                'Write unit tests for all classes',
            ],
            'avoid' => [
                'Global variables and functions',
                'Static methods for stateful operations',
                'Direct database queries without abstraction',
                'Hardcoded configuration values',
            ],
            'tools' => [
                'PHPStan for static analysis',
                'Rector for automated refactoring',
                'PHP CS Fixer or Pint for formatting',
                'Pest or PHPUnit for testing',
            ],
        ];
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $path): self
    {
        return new self(FrameworkDetector::fromPath($path));
    }
}
