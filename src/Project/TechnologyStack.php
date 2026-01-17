<?php

declare(strict_types=1);

namespace LaraForge\Project;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Technology Stack Detector
 *
 * Auto-detects the technology stack of a project including:
 * - PHP version
 * - Laravel version
 * - Package dependencies and their versions
 * - Testing frameworks
 * - Database drivers
 * - Frontend stack
 *
 * This information is used to:
 * 1. Query version-specific documentation via MCP
 * 2. Generate version-appropriate code stubs
 * 3. Provide relevant best practices
 */
final class TechnologyStack
{
    private ?array $composerJson = null;

    private ?array $composerLock = null;

    private ?array $packageJson = null;

    public function __construct(
        private readonly string $projectPath,
        private readonly Filesystem $filesystem = new Filesystem,
    ) {}

    /**
     * Detect and return the full technology stack.
     *
     * @return array<string, mixed>
     */
    public function detect(): array
    {
        return [
            'php' => $this->detectPhpVersion(),
            'laravel' => $this->detectLaravelVersion(),
            'framework' => $this->detectFramework(),
            'packages' => $this->detectPackages(),
            'testing' => $this->detectTestingStack(),
            'database' => $this->detectDatabaseStack(),
            'frontend' => $this->detectFrontendStack(),
            'api' => $this->detectApiStack(),
            'queue' => $this->detectQueueStack(),
            'cache' => $this->detectCacheStack(),
        ];
    }

    /**
     * Get a summary suitable for MCP documentation queries.
     *
     * @return array<string, string>
     */
    public function forDocumentationQuery(): array
    {
        $stack = $this->detect();

        return [
            'php_version' => $stack['php']['version'] ?? 'unknown',
            'laravel_version' => $stack['laravel']['version'] ?? 'unknown',
            'laravel_major' => $stack['laravel']['major'] ?? 'unknown',
            'testing_framework' => $stack['testing']['framework'] ?? 'phpunit',
            'database_driver' => $stack['database']['default'] ?? 'mysql',
            'has_livewire' => $stack['frontend']['livewire'] ? 'yes' : 'no',
            'has_inertia' => $stack['frontend']['inertia'] ? 'yes' : 'no',
            'api_style' => $stack['api']['sanctum'] ? 'sanctum' : ($stack['api']['passport'] ? 'passport' : 'none'),
        ];
    }

    /**
     * Build a context string for AI prompts.
     */
    public function toContextString(): string
    {
        $stack = $this->detect();
        $lines = [
            '## Project Technology Stack',
            '',
            '### Core',
            "- PHP: {$stack['php']['version']} (constraint: {$stack['php']['constraint']})",
            "- Laravel: {$stack['laravel']['version']} (major: {$stack['laravel']['major']})",
            '',
        ];

        if (! empty($stack['testing']['framework'])) {
            $lines[] = '### Testing';
            $lines[] = "- Framework: {$stack['testing']['framework']}";
            if ($stack['testing']['pest']) {
                $lines[] = '- Pest: Yes';
            }
            if ($stack['testing']['dusk']) {
                $lines[] = '- Laravel Dusk: Yes (browser testing)';
            }
            $lines[] = '';
        }

        if (! empty($stack['database']['default'])) {
            $lines[] = '### Database';
            $lines[] = "- Default Driver: {$stack['database']['default']}";
            $lines[] = '';
        }

        if ($stack['frontend']['livewire'] || $stack['frontend']['inertia'] || $stack['frontend']['vite']) {
            $lines[] = '### Frontend';
            if ($stack['frontend']['livewire']) {
                $lines[] = '- Livewire: Yes';
            }
            if ($stack['frontend']['inertia']) {
                $lines[] = "- Inertia.js: Yes (with {$stack['frontend']['inertia_stack']})";
            }
            if ($stack['frontend']['vite']) {
                $lines[] = '- Vite: Yes';
            }
            $lines[] = '';
        }

        if ($stack['api']['sanctum'] || $stack['api']['passport']) {
            $lines[] = '### API';
            if ($stack['api']['sanctum']) {
                $lines[] = '- Laravel Sanctum: Yes (token-based auth)';
            }
            if ($stack['api']['passport']) {
                $lines[] = '- Laravel Passport: Yes (OAuth2)';
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Get documentation URLs for the detected stack.
     *
     * @return array<string, string>
     */
    public function getDocumentationUrls(): array
    {
        $stack = $this->detect();
        $laravelMajor = $stack['laravel']['major'] ?? '11';

        $urls = [
            'laravel' => "https://laravel.com/docs/{$laravelMajor}.x",
            'php' => 'https://www.php.net/manual/en/',
        ];

        if ($stack['testing']['pest']) {
            $urls['pest'] = 'https://pestphp.com/docs';
        }

        if ($stack['frontend']['livewire']) {
            $urls['livewire'] = 'https://livewire.laravel.com/docs';
        }

        if ($stack['frontend']['inertia']) {
            $urls['inertia'] = 'https://inertiajs.com';
        }

        if ($stack['api']['sanctum']) {
            $urls['sanctum'] = "https://laravel.com/docs/{$laravelMajor}.x/sanctum";
        }

        return $urls;
    }

    /**
     * Check if a specific feature/package is available.
     */
    public function has(string $feature): bool
    {
        return match ($feature) {
            'pest' => $this->hasPackage('pestphp/pest'),
            'phpunit' => $this->hasPackage('phpunit/phpunit'),
            'dusk' => $this->hasPackage('laravel/dusk'),
            'livewire' => $this->hasPackage('livewire/livewire'),
            'inertia' => $this->hasPackage('inertiajs/inertia-laravel'),
            'sanctum' => $this->hasPackage('laravel/sanctum'),
            'passport' => $this->hasPackage('laravel/passport'),
            'horizon' => $this->hasPackage('laravel/horizon'),
            'telescope' => $this->hasPackage('laravel/telescope'),
            'nova' => $this->hasPackage('laravel/nova'),
            'spark' => $this->hasPackage('laravel/spark-stripe') || $this->hasPackage('laravel/spark-paddle'),
            'jetstream' => $this->hasPackage('laravel/jetstream'),
            'breeze' => $this->hasPackage('laravel/breeze'),
            'fortify' => $this->hasPackage('laravel/fortify'),
            'cashier' => $this->hasPackage('laravel/cashier'),
            'scout' => $this->hasPackage('laravel/scout'),
            'socialite' => $this->hasPackage('laravel/socialite'),
            'vite' => $this->hasPackage('laravel/vite-plugin') || $this->hasNodePackage('laravel-vite-plugin'),
            'tailwind' => $this->hasNodePackage('tailwindcss'),
            'vue' => $this->hasNodePackage('vue'),
            'react' => $this->hasNodePackage('react'),
            'typescript' => $this->hasNodePackage('typescript'),
            'saloon' => $this->hasPackage('saloonphp/saloon'),
            'spatie-permissions' => $this->hasPackage('spatie/laravel-permission'),
            'spatie-media' => $this->hasPackage('spatie/laravel-medialibrary'),
            default => $this->hasPackage($feature),
        };
    }

    /**
     * Get the version of a specific package.
     */
    public function getPackageVersion(string $package): ?string
    {
        $lock = $this->getComposerLock();

        foreach ($lock['packages'] ?? [] as $pkg) {
            if ($pkg['name'] === $package) {
                return ltrim($pkg['version'], 'v');
            }
        }

        foreach ($lock['packages-dev'] ?? [] as $pkg) {
            if ($pkg['name'] === $package) {
                return ltrim($pkg['version'], 'v');
            }
        }

        return null;
    }

    private function detectPhpVersion(): array
    {
        $composer = $this->getComposerJson();
        $constraint = $composer['require']['php'] ?? '^8.1';

        // Get actual PHP version from lock file or runtime
        $lock = $this->getComposerLock();
        $platformPhp = $lock['platform']['php'] ?? PHP_VERSION;

        return [
            'constraint' => $constraint,
            'version' => $platformPhp,
            'major' => (int) explode('.', $platformPhp)[0],
            'minor' => (int) (explode('.', $platformPhp)[1] ?? 0),
        ];
    }

    private function detectLaravelVersion(): array
    {
        $version = $this->getPackageVersion('laravel/framework');

        if (! $version) {
            return [
                'version' => 'unknown',
                'major' => 'unknown',
                'constraint' => 'unknown',
            ];
        }

        $parts = explode('.', $version);
        $major = $parts[0] !== '' ? $parts[0] : 'unknown';

        return [
            'version' => $version,
            'major' => $major,
            'minor' => $parts[1] ?? '0',
            'constraint' => $this->getComposerJson()['require']['laravel/framework'] ?? "^{$major}.0",
        ];
    }

    private function detectFramework(): array
    {
        $hasLaravel = $this->hasPackage('laravel/framework');
        $hasLumen = $this->hasPackage('laravel/lumen-framework');

        return [
            'name' => $hasLumen ? 'lumen' : ($hasLaravel ? 'laravel' : 'unknown'),
            'is_laravel' => $hasLaravel,
            'is_lumen' => $hasLumen,
        ];
    }

    private function detectPackages(): array
    {
        $composer = $this->getComposerJson();

        return [
            'require' => array_keys($composer['require'] ?? []),
            'require_dev' => array_keys($composer['require-dev'] ?? []),
        ];
    }

    private function detectTestingStack(): array
    {
        $hasPest = $this->hasPackage('pestphp/pest');
        $hasPhpunit = $this->hasPackage('phpunit/phpunit');
        $hasDusk = $this->hasPackage('laravel/dusk');

        return [
            'framework' => $hasPest ? 'pest' : ($hasPhpunit ? 'phpunit' : 'unknown'),
            'pest' => $hasPest,
            'pest_version' => $hasPest ? $this->getPackageVersion('pestphp/pest') : null,
            'phpunit' => $hasPhpunit,
            'dusk' => $hasDusk,
            'mockery' => $this->hasPackage('mockery/mockery'),
            'faker' => $this->hasPackage('fakerphp/faker'),
        ];
    }

    private function detectDatabaseStack(): array
    {
        // Try to detect from .env or config
        $envPath = $this->projectPath.'/.env';
        $default = 'mysql';

        if ($this->filesystem->exists($envPath)) {
            $env = file_get_contents($envPath);
            if (preg_match('/^DB_CONNECTION=(.+)$/m', $env, $matches)) {
                $default = trim($matches[1]);
            }
        }

        return [
            'default' => $default,
            'sqlite' => $default === 'sqlite',
            'mysql' => $default === 'mysql',
            'pgsql' => $default === 'pgsql',
            'sqlsrv' => $default === 'sqlsrv',
        ];
    }

    private function detectFrontendStack(): array
    {
        return [
            'livewire' => $this->hasPackage('livewire/livewire'),
            'livewire_version' => $this->getPackageVersion('livewire/livewire'),
            'inertia' => $this->hasPackage('inertiajs/inertia-laravel'),
            'inertia_stack' => $this->detectInertiaStack(),
            'vite' => $this->hasPackage('laravel/vite-plugin') || $this->hasNodePackage('laravel-vite-plugin'),
            'tailwind' => $this->hasNodePackage('tailwindcss'),
            'vue' => $this->hasNodePackage('vue'),
            'react' => $this->hasNodePackage('react'),
            'alpine' => $this->hasNodePackage('alpinejs'),
            'typescript' => $this->hasNodePackage('typescript'),
        ];
    }

    private function detectApiStack(): array
    {
        return [
            'sanctum' => $this->hasPackage('laravel/sanctum'),
            'passport' => $this->hasPackage('laravel/passport'),
            'fortify' => $this->hasPackage('laravel/fortify'),
        ];
    }

    private function detectQueueStack(): array
    {
        return [
            'horizon' => $this->hasPackage('laravel/horizon'),
            'redis' => $this->hasPackage('predis/predis') || extension_loaded('redis'),
        ];
    }

    private function detectCacheStack(): array
    {
        return [
            'redis' => $this->hasPackage('predis/predis') || extension_loaded('redis'),
            'memcached' => extension_loaded('memcached'),
        ];
    }

    private function detectInertiaStack(): string
    {
        if ($this->hasNodePackage('vue')) {
            return 'vue';
        }

        if ($this->hasNodePackage('react')) {
            return 'react';
        }

        if ($this->hasNodePackage('svelte')) {
            return 'svelte';
        }

        return 'unknown';
    }

    private function hasPackage(string $package): bool
    {
        $composer = $this->getComposerJson();

        return isset($composer['require'][$package])
            || isset($composer['require-dev'][$package]);
    }

    private function hasNodePackage(string $package): bool
    {
        $packageJson = $this->getPackageJson();

        return isset($packageJson['dependencies'][$package])
            || isset($packageJson['devDependencies'][$package]);
    }

    private function getComposerJson(): array
    {
        if ($this->composerJson === null) {
            $path = $this->projectPath.'/composer.json';
            $this->composerJson = $this->filesystem->exists($path)
                ? json_decode(file_get_contents($path), true) ?? []
                : [];
        }

        return $this->composerJson;
    }

    private function getComposerLock(): array
    {
        if ($this->composerLock === null) {
            $path = $this->projectPath.'/composer.lock';
            $this->composerLock = $this->filesystem->exists($path)
                ? json_decode(file_get_contents($path), true) ?? []
                : [];
        }

        return $this->composerLock;
    }

    private function getPackageJson(): array
    {
        if ($this->packageJson === null) {
            $path = $this->projectPath.'/package.json';
            $this->packageJson = $this->filesystem->exists($path)
                ? json_decode(file_get_contents($path), true) ?? []
                : [];
        }

        return $this->packageJson;
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $path): self
    {
        return new self($path);
    }
}
