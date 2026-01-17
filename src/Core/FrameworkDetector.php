<?php

declare(strict_types=1);

namespace LaraForge\Core;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Framework Detector
 *
 * Detects which PHP framework (if any) is being used in a project.
 * Supports Laravel, Symfony, and generic PHP projects.
 */
final class FrameworkDetector
{
    private Filesystem $filesystem;

    /**
     * Supported frameworks and their detection signatures.
     *
     * @var array<string, array<string, mixed>>
     */
    private const FRAMEWORKS = [
        'laravel' => [
            'composer_packages' => ['laravel/framework'],
            'files' => ['artisan', 'bootstrap/app.php'],
            'directories' => ['app/Http', 'app/Models'],
        ],
        'lumen' => [
            'composer_packages' => ['laravel/lumen-framework'],
            'files' => ['artisan', 'bootstrap/app.php'],
            'directories' => ['app/Http'],
        ],
        'symfony' => [
            'composer_packages' => ['symfony/framework-bundle', 'symfony/symfony'],
            'files' => ['bin/console', 'config/bundles.php'],
            'directories' => ['src/Controller', 'src/Entity'],
        ],
        'slim' => [
            'composer_packages' => ['slim/slim'],
            'files' => ['public/index.php'],
            'directories' => [],
        ],
        'codeigniter' => [
            'composer_packages' => ['codeigniter4/framework'],
            'files' => ['spark'],
            'directories' => ['app/Controllers'],
        ],
        'yii' => [
            'composer_packages' => ['yiisoft/yii2'],
            'files' => ['yii'],
            'directories' => ['controllers', 'models'],
        ],
        'cakephp' => [
            'composer_packages' => ['cakephp/cakephp'],
            'files' => ['bin/cake'],
            'directories' => ['src/Controller'],
        ],
    ];

    public function __construct(
        private readonly string $projectPath,
    ) {
        $this->filesystem = new Filesystem;
    }

    /**
     * Detect the framework used in the project.
     *
     * @return array{framework: string, version: ?string, confidence: float, features: array<string, bool>}
     */
    public function detect(): array
    {
        $composerJson = $this->getComposerJson();
        $composerLock = $this->getComposerLock();

        foreach (self::FRAMEWORKS as $framework => $signatures) {
            $score = $this->calculateConfidence($signatures, $composerJson);

            if ($score > 0.5) {
                return [
                    'framework' => $framework,
                    'version' => $this->getFrameworkVersion($framework, $composerLock),
                    'confidence' => $score,
                    'features' => $this->detectFrameworkFeatures($framework, $composerJson),
                ];
            }
        }

        // Generic PHP project
        return [
            'framework' => 'generic',
            'version' => null,
            'confidence' => 1.0,
            'features' => $this->detectGenericFeatures($composerJson),
        ];
    }

    /**
     * Check if project uses a specific framework.
     */
    public function isFramework(string $framework): bool
    {
        return $this->detect()['framework'] === $framework;
    }

    /**
     * Check if project is Laravel-based (Laravel or Lumen).
     */
    public function isLaravel(): bool
    {
        $framework = $this->detect()['framework'];

        return \in_array($framework, ['laravel', 'lumen'], true);
    }

    /**
     * Check if project is Symfony-based.
     */
    public function isSymfony(): bool
    {
        return $this->detect()['framework'] === 'symfony';
    }

    /**
     * Check if project is a generic PHP project.
     */
    public function isGeneric(): bool
    {
        return $this->detect()['framework'] === 'generic';
    }

    /**
     * Get PHP version constraint from composer.json.
     */
    public function getPhpVersion(): string
    {
        $composer = $this->getComposerJson();

        return $composer['require']['php'] ?? '^8.1';
    }

    /**
     * Get project namespace from composer.json.
     */
    public function getProjectNamespace(): ?string
    {
        $composer = $this->getComposerJson();
        $autoload = $composer['autoload']['psr-4'] ?? [];

        // Return the first PSR-4 namespace
        foreach ($autoload as $namespace => $path) {
            return rtrim($namespace, '\\');
        }

        return null;
    }

    /**
     * Get recommended directory structure for the framework.
     *
     * @return array<string, string>
     */
    public function getDirectoryStructure(): array
    {
        $framework = $this->detect()['framework'];

        return match ($framework) {
            'laravel' => [
                'controllers' => 'app/Http/Controllers',
                'models' => 'app/Models',
                'views' => 'resources/views',
                'tests' => 'tests',
                'config' => 'config',
                'routes' => 'routes',
                'migrations' => 'database/migrations',
            ],
            'symfony' => [
                'controllers' => 'src/Controller',
                'entities' => 'src/Entity',
                'templates' => 'templates',
                'tests' => 'tests',
                'config' => 'config',
            ],
            'generic' => [
                'source' => 'src',
                'tests' => 'tests',
                'config' => 'config',
                'public' => 'public',
            ],
            default => [
                'source' => 'src',
                'tests' => 'tests',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $signatures
     * @param  array<string, mixed>  $composerJson
     */
    private function calculateConfidence(array $signatures, array $composerJson): float
    {
        $score = 0.0;
        $checks = 0;

        // Check composer packages
        $require = array_merge(
            $composerJson['require'] ?? [],
            $composerJson['require-dev'] ?? [],
        );

        foreach ($signatures['composer_packages'] as $package) {
            $checks++;
            if (isset($require[$package])) {
                $score += 0.5; // Composer package is strongest indicator
            }
        }

        // Check files
        foreach ($signatures['files'] as $file) {
            $checks++;
            if ($this->filesystem->exists($this->projectPath.'/'.$file)) {
                $score += 0.3;
            }
        }

        // Check directories
        foreach ($signatures['directories'] as $dir) {
            $checks++;
            if ($this->filesystem->exists($this->projectPath.'/'.$dir)) {
                $score += 0.2;
            }
        }

        return $checks > 0 ? $score / $checks : 0.0;
    }

    /**
     * @param  array<string, mixed>  $composerLock
     */
    private function getFrameworkVersion(string $framework, array $composerLock): ?string
    {
        $packageNames = match ($framework) {
            'laravel' => ['laravel/framework'],
            'lumen' => ['laravel/lumen-framework'],
            'symfony' => ['symfony/framework-bundle', 'symfony/symfony'],
            'slim' => ['slim/slim'],
            default => [],
        };

        foreach ($composerLock['packages'] ?? [] as $package) {
            if (\in_array($package['name'], $packageNames, true)) {
                return ltrim($package['version'], 'v');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $composerJson
     * @return array<string, bool>
     */
    private function detectFrameworkFeatures(string $framework, array $composerJson): array
    {
        $require = array_merge(
            $composerJson['require'] ?? [],
            $composerJson['require-dev'] ?? [],
        );

        if ($framework === 'laravel') {
            return [
                'sanctum' => isset($require['laravel/sanctum']),
                'passport' => isset($require['laravel/passport']),
                'livewire' => isset($require['livewire/livewire']),
                'inertia' => isset($require['inertiajs/inertia-laravel']),
                'horizon' => isset($require['laravel/horizon']),
                'telescope' => isset($require['laravel/telescope']),
                'pest' => isset($require['pestphp/pest']),
                'dusk' => isset($require['laravel/dusk']),
            ];
        }

        if ($framework === 'symfony') {
            return [
                'doctrine' => isset($require['doctrine/orm']) || isset($require['doctrine/doctrine-bundle']),
                'twig' => isset($require['twig/twig']) || isset($require['symfony/twig-bundle']),
                'api_platform' => isset($require['api-platform/core']),
                'messenger' => isset($require['symfony/messenger']),
            ];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $composerJson
     * @return array<string, bool>
     */
    private function detectGenericFeatures(array $composerJson): array
    {
        $require = array_merge(
            $composerJson['require'] ?? [],
            $composerJson['require-dev'] ?? [],
        );

        return [
            'pest' => isset($require['pestphp/pest']),
            'phpunit' => isset($require['phpunit/phpunit']),
            'phpstan' => isset($require['phpstan/phpstan']),
            'rector' => isset($require['rector/rector']),
            'psr7' => isset($require['psr/http-message']),
            'psr15' => isset($require['psr/http-server-handler']),
            'doctrine' => isset($require['doctrine/orm']),
            'monolog' => isset($require['monolog/monolog']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getComposerJson(): array
    {
        $path = $this->projectPath.'/composer.json';

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getComposerLock(): array
    {
        $path = $this->projectPath.'/composer.lock';

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?? [];
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $path): self
    {
        return new self($path);
    }
}
