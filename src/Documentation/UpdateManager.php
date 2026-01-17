<?php

declare(strict_types=1);

namespace LaraForge\Documentation;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Update Manager
 *
 * Manages updates to internal configurations and rules based on
 * external documentation changes and open source project updates.
 */
final class UpdateManager
{
    private Filesystem $filesystem;

    private DocumentationSync $docSync;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $updateLog = [];

    /**
     * Updatable configuration types.
     *
     * @var array<string, array<string, mixed>>
     */
    private const UPDATABLE_CONFIGS = [
        'security_rules' => [
            'description' => 'Security scanning rules (OWASP, framework-specific)',
            'sources' => ['laravel', 'php-fig'],
            'file' => 'security_rules.json',
        ],
        'coding_standards' => [
            'description' => 'Coding standards and best practices',
            'sources' => ['php-fig', 'phpstan'],
            'file' => 'coding_standards.json',
        ],
        'framework_patterns' => [
            'description' => 'Framework-specific patterns and conventions',
            'sources' => ['laravel', 'livewire', 'inertia'],
            'file' => 'framework_patterns.json',
        ],
        'component_patterns' => [
            'description' => 'UI component patterns and best practices',
            'sources' => ['tailwind', 'alpine', 'vue', 'react'],
            'file' => 'component_patterns.json',
        ],
        'package_versions' => [
            'description' => 'Recommended package versions',
            'sources' => ['packagist', 'npm'],
            'file' => 'package_versions.json',
        ],
    ];

    public function __construct(
        private readonly string $configPath,
        ?DocumentationSync $docSync = null,
    ) {
        $this->filesystem = new Filesystem;
        $this->docSync = $docSync ?? DocumentationSync::fromPath(dirname($configPath));
        $this->loadUpdateLog();
    }

    /**
     * Check for available updates.
     *
     * @return array<string, array<string, mixed>>
     */
    public function checkForUpdates(): array
    {
        $updates = [];

        foreach (self::UPDATABLE_CONFIGS as $configType => $config) {
            $status = $this->checkConfigUpdate($configType, $config);
            if ($status['has_update']) {
                $updates[$configType] = $status;
            }
        }

        return $updates;
    }

    /**
     * Check if a specific config needs updating.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function checkConfigUpdate(string $configType, array $config): array
    {
        $currentVersion = $this->getCurrentVersion($configType);
        $lastUpdate = $this->updateLog[$configType]['updated_at'] ?? null;

        // Check if sources have newer content
        $sourcesNeedUpdate = false;
        foreach ($config['sources'] as $sourceId) {
            if ($this->docSync->needsUpdate($sourceId, 'index')) {
                $sourcesNeedUpdate = true;
                break;
            }
        }

        // Check if it's been more than 7 days since last update
        $staleUpdate = $lastUpdate !== null && strtotime($lastUpdate) < strtotime('-7 days');

        return [
            'config_type' => $configType,
            'description' => $config['description'],
            'current_version' => $currentVersion,
            'last_updated' => $lastUpdate,
            'has_update' => $sourcesNeedUpdate || $staleUpdate || $currentVersion === null,
            'sources' => $config['sources'],
        ];
    }

    /**
     * Apply updates for a specific config type.
     *
     * @return array<string, mixed>
     */
    public function applyUpdate(string $configType): array
    {
        $config = self::UPDATABLE_CONFIGS[$configType] ?? null;

        if ($config === null) {
            return ['success' => false, 'error' => "Unknown config type: {$configType}"];
        }

        $result = match ($configType) {
            'security_rules' => $this->updateSecurityRules($config),
            'coding_standards' => $this->updateCodingStandards($config),
            'framework_patterns' => $this->updateFrameworkPatterns($config),
            'component_patterns' => $this->updateComponentPatterns($config),
            'package_versions' => $this->updatePackageVersions($config),
            default => ['success' => false, 'error' => 'Updater not implemented'],
        };

        if ($result['success']) {
            $this->logUpdate($configType, $result);
        }

        return $result;
    }

    /**
     * Apply all available updates.
     *
     * @return array<string, array<string, mixed>>
     */
    public function applyAllUpdates(): array
    {
        $results = [];

        foreach (array_keys(self::UPDATABLE_CONFIGS) as $configType) {
            $results[$configType] = $this->applyUpdate($configType);
        }

        return $results;
    }

    /**
     * Get update history.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getUpdateHistory(): array
    {
        return $this->updateLog;
    }

    /**
     * Get current config version.
     */
    public function getCurrentVersion(string $configType): ?string
    {
        return $this->updateLog[$configType]['version'] ?? null;
    }

    /**
     * Get updatable configuration types.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getUpdatableConfigs(): array
    {
        return self::UPDATABLE_CONFIGS;
    }

    /**
     * Refresh documentation cache for a source.
     *
     * @return array<string, mixed>
     */
    public function refreshSource(string $sourceId): array
    {
        $this->docSync->clearCache($sourceId);

        return [
            'success' => true,
            'source' => $sourceId,
            'message' => "Cache cleared for {$sourceId}",
        ];
    }

    /**
     * Get recommended package versions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRecommendedVersions(): array
    {
        $configFile = $this->configPath.'/package_versions.json';

        if ($this->filesystem->exists($configFile)) {
            $content = file_get_contents($configFile);
            if ($content !== false) {
                return json_decode($content, true) ?? [];
            }
        }

        return $this->getDefaultPackageVersions();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function updateSecurityRules(array $config): array
    {
        // Fetch latest security recommendations
        $rules = $this->buildSecurityRules();

        $this->saveConfig('security_rules.json', $rules);

        return [
            'success' => true,
            'rules_count' => count($rules['rules'] ?? []),
            'sources' => $config['sources'],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function updateCodingStandards(array $config): array
    {
        $standards = $this->buildCodingStandards();

        $this->saveConfig('coding_standards.json', $standards);

        return [
            'success' => true,
            'standards_count' => count($standards['standards'] ?? []),
            'sources' => $config['sources'],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function updateFrameworkPatterns(array $config): array
    {
        $patterns = $this->buildFrameworkPatterns();

        $this->saveConfig('framework_patterns.json', $patterns);

        return [
            'success' => true,
            'patterns_count' => count($patterns['patterns'] ?? []),
            'sources' => $config['sources'],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function updateComponentPatterns(array $config): array
    {
        $patterns = $this->buildComponentPatterns();

        $this->saveConfig('component_patterns.json', $patterns);

        return [
            'success' => true,
            'patterns_count' => count($patterns['patterns'] ?? []),
            'sources' => $config['sources'],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function updatePackageVersions(array $config): array
    {
        $packages = $this->fetchPackageVersions();

        $this->saveConfig('package_versions.json', $packages);

        return [
            'success' => true,
            'packages_count' => count($packages['php'] ?? []) + count($packages['npm'] ?? []),
            'sources' => $config['sources'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSecurityRules(): array
    {
        return [
            'version' => date('Y-m-d'),
            'sources' => [
                'laravel' => $this->docSync->getDocUrl('laravel', 'security', '11.x'),
                'owasp' => 'https://owasp.org/www-project-top-ten/',
            ],
            'rules' => [
                'sql_injection' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'queries', '11.x'),
                    'recommendation' => 'Use Eloquent ORM or query builder with parameter binding',
                ],
                'xss' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'blade', '11.x'),
                    'recommendation' => 'Use {{ }} for escaped output, {!! !!} only for trusted HTML',
                ],
                'csrf' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'csrf', '11.x'),
                    'recommendation' => 'Use @csrf directive in all forms',
                ],
                'mass_assignment' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'eloquent', '11.x'),
                    'recommendation' => 'Define $fillable or $guarded on all models',
                ],
                'authentication' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'authentication', '11.x'),
                    'recommendation' => 'Use Laravel built-in authentication',
                ],
                'authorization' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'authorization', '11.x'),
                    'recommendation' => 'Use policies for model-based authorization',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCodingStandards(): array
    {
        return [
            'version' => date('Y-m-d'),
            'sources' => [
                'psr-12' => $this->docSync->getDocUrl('php-fig', 'psr-12'),
                'phpstan' => $this->docSync->getDocUrl('phpstan', 'getting-started'),
            ],
            'standards' => [
                'psr-12' => [
                    'doc_url' => $this->docSync->getDocUrl('php-fig', 'psr-12'),
                    'tool' => 'Laravel Pint',
                ],
                'strict_types' => [
                    'recommendation' => 'Use declare(strict_types=1) in all files',
                ],
                'static_analysis' => [
                    'doc_url' => $this->docSync->getDocUrl('phpstan', 'getting-started'),
                    'tool' => 'PHPStan level 8',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFrameworkPatterns(): array
    {
        return [
            'version' => date('Y-m-d'),
            'sources' => [
                'laravel' => $this->docSync->getDocUrl('laravel', 'installation', '11.x'),
                'livewire' => $this->docSync->getDocUrl('livewire', 'quickstart'),
                'inertia' => $this->docSync->getDocUrl('inertia', 'installation'),
            ],
            'patterns' => [
                'controllers' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'controllers', '11.x'),
                    'recommendation' => 'Use single-action controllers for complex actions',
                ],
                'form_requests' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'validation', '11.x'),
                    'recommendation' => 'Use FormRequest classes for validation',
                ],
                'api_resources' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'eloquent-resources', '11.x'),
                    'recommendation' => 'Use API Resources for JSON responses',
                ],
                'policies' => [
                    'doc_url' => $this->docSync->getDocUrl('laravel', 'authorization', '11.x'),
                    'recommendation' => 'Use Policies for model authorization',
                ],
                'livewire' => [
                    'doc_url' => $this->docSync->getDocUrl('livewire', 'components'),
                    'recommendation' => 'Follow Livewire component naming conventions',
                ],
                'inertia' => [
                    'doc_url' => $this->docSync->getDocUrl('inertia', 'pages'),
                    'recommendation' => 'Use Inertia responses for SPA pages',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComponentPatterns(): array
    {
        return [
            'version' => date('Y-m-d'),
            'sources' => [
                'tailwind' => $this->docSync->getDocUrl('tailwind', 'installation'),
                'alpine' => $this->docSync->getDocUrl('alpine', 'start'),
                'vue' => $this->docSync->getDocUrl('vue', 'introduction'),
                'react' => $this->docSync->getDocUrl('react', 'quick-start'),
            ],
            'patterns' => [
                'tailwind' => [
                    'doc_url' => $this->docSync->getDocUrl('tailwind', 'configuration'),
                    'recommendation' => 'Use Tailwind design tokens for consistency',
                ],
                'alpine' => [
                    'doc_url' => $this->docSync->getDocUrl('alpine', 'x-data'),
                    'recommendation' => 'Use Alpine for simple interactivity',
                ],
                'vue_composition' => [
                    'doc_url' => $this->docSync->getDocUrl('vue', 'composables'),
                    'recommendation' => 'Use Composition API with script setup',
                ],
                'react_hooks' => [
                    'doc_url' => $this->docSync->getDocUrl('react', 'hooks'),
                    'recommendation' => 'Use hooks for state and effects',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPackageVersions(): array
    {
        $phpPackages = [
            'laravel/framework' => $this->fetchLatestVersion('laravel', 'framework'),
            'livewire/livewire' => $this->fetchLatestVersion('livewire', 'livewire'),
            'pestphp/pest' => $this->fetchLatestVersion('pestphp', 'pest'),
            'phpstan/phpstan' => $this->fetchLatestVersion('phpstan', 'phpstan'),
            'laravel/pint' => $this->fetchLatestVersion('laravel', 'pint'),
            'spatie/laravel-permission' => $this->fetchLatestVersion('spatie', 'laravel-permission'),
            'saloonphp/saloon' => $this->fetchLatestVersion('saloonphp', 'saloon'),
        ];

        return [
            'version' => date('Y-m-d'),
            'php' => array_filter($phpPackages),
            'npm' => [
                // These would be fetched from npm registry
                'tailwindcss' => '^3.4',
                'alpinejs' => '^3.14',
                '@inertiajs/vue3' => '^1.0',
                '@inertiajs/react' => '^1.0',
            ],
        ];
    }

    private function fetchLatestVersion(string $vendor, string $package): ?string
    {
        try {
            $info = $this->docSync->fetchPackageInfo($vendor, $package);

            return $info['version'] ?? $info['versions'][0]['version'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultPackageVersions(): array
    {
        return [
            'version' => '2024-01-01',
            'php' => [
                'laravel/framework' => '^11.0',
                'livewire/livewire' => '^3.0',
                'pestphp/pest' => '^3.0',
            ],
            'npm' => [
                'tailwindcss' => '^3.4',
                'alpinejs' => '^3.14',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveConfig(string $filename, array $data): void
    {
        if (! $this->filesystem->exists($this->configPath)) {
            $this->filesystem->mkdir($this->configPath);
        }

        file_put_contents(
            $this->configPath.'/'.$filename,
            json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function logUpdate(string $configType, array $result): void
    {
        $this->updateLog[$configType] = [
            'updated_at' => date('c'),
            'version' => date('Y-m-d'),
            'result' => $result,
        ];

        $this->saveUpdateLog();
    }

    private function loadUpdateLog(): void
    {
        $logFile = $this->configPath.'/update_log.json';

        if ($this->filesystem->exists($logFile)) {
            $content = file_get_contents($logFile);
            if ($content !== false) {
                $this->updateLog = json_decode($content, true) ?? [];
            }
        }
    }

    private function saveUpdateLog(): void
    {
        if (! $this->filesystem->exists($this->configPath)) {
            $this->filesystem->mkdir($this->configPath);
        }

        file_put_contents(
            $this->configPath.'/update_log.json',
            json_encode($this->updateLog, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $projectPath): self
    {
        return new self($projectPath.'/.laraforge/config');
    }
}
