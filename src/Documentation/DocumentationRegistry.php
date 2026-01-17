<?php

declare(strict_types=1);

namespace LaraForge\Documentation;

/**
 * Documentation Registry
 *
 * Central registry of all external documentation sources.
 * Provides access to Laravel, PHP, and frontend framework documentation.
 */
final class DocumentationRegistry
{
    /**
     * @var array<string, DocumentationSource>
     */
    private array $sources = [];

    public function __construct()
    {
        $this->registerDefaultSources();
    }

    /**
     * Register a documentation source.
     */
    public function register(DocumentationSource $source): void
    {
        $this->sources[$source->getIdentifier()] = $source;
    }

    /**
     * Get a source by identifier.
     */
    public function get(string $identifier): ?DocumentationSource
    {
        return $this->sources[$identifier] ?? null;
    }

    /**
     * Get all registered sources.
     *
     * @return array<string, DocumentationSource>
     */
    public function all(): array
    {
        return $this->sources;
    }

    /**
     * Check if a source exists.
     */
    public function has(string $identifier): bool
    {
        return isset($this->sources[$identifier]);
    }

    /**
     * Get sources by category.
     *
     * @return array<string, DocumentationSource>
     */
    public function getByCategory(string $category): array
    {
        return array_filter(
            $this->sources,
            fn (DocumentationSource $source) => ($source->getMetadata()['category'] ?? '') === $category
        );
    }

    private function registerDefaultSources(): void
    {
        // Laravel Documentation
        $this->register(new DocumentationSource(
            identifier: 'laravel',
            name: 'Laravel Framework',
            baseUrl: 'https://laravel.com/docs/{version}',
            endpoints: [
                'installation' => 'installation',
                'configuration' => 'configuration',
                'routing' => 'routing',
                'controllers' => 'controllers',
                'requests' => 'requests',
                'responses' => 'responses',
                'views' => 'views',
                'blade' => 'blade',
                'validation' => 'validation',
                'eloquent' => 'eloquent',
                'eloquent-relationships' => 'eloquent-relationships',
                'eloquent-collections' => 'eloquent-collections',
                'eloquent-resources' => 'eloquent-resources',
                'database' => 'database',
                'queries' => 'queries',
                'migrations' => 'migrations',
                'seeding' => 'seeding',
                'testing' => 'testing',
                'http-tests' => 'http-tests',
                'console-tests' => 'console-tests',
                'authentication' => 'authentication',
                'authorization' => 'authorization',
                'encryption' => 'encryption',
                'hashing' => 'hashing',
                'queues' => 'queues',
                'events' => 'events',
                'broadcasting' => 'broadcasting',
                'cache' => 'cache',
                'collections' => 'collections',
                'filesystem' => 'filesystem',
                'helpers' => 'helpers',
                'mail' => 'mail',
                'notifications' => 'notifications',
                'packages' => 'packages',
                'scheduling' => 'scheduling',
            ],
            versionPattern: '{version}',
            metadata: [
                'category' => 'framework',
                'language' => 'php',
                'latest_version' => '11.x',
            ],
        ));

        // Laravel API Documentation (JSON)
        $this->register(new DocumentationSource(
            identifier: 'laravel-api',
            name: 'Laravel API Reference',
            baseUrl: 'https://laravel.com/api/{version}',
            endpoints: [
                'index' => 'index.html',
            ],
            versionPattern: '{version}',
            metadata: [
                'category' => 'api-reference',
                'language' => 'php',
            ],
        ));

        // PHP Manual
        $this->register(new DocumentationSource(
            identifier: 'php',
            name: 'PHP Manual',
            baseUrl: 'https://www.php.net/manual/en',
            endpoints: [
                'language-reference' => 'langref.php',
                'functions' => 'funcref.php',
                'classes' => 'class.php',
                'security' => 'security.php',
                'features' => 'features.php',
                'appendices' => 'appendices.php',
            ],
            metadata: [
                'category' => 'language',
                'language' => 'php',
            ],
        ));

        // PSR Standards
        $this->register(new DocumentationSource(
            identifier: 'php-fig',
            name: 'PHP-FIG PSR Standards',
            baseUrl: 'https://www.php-fig.org',
            endpoints: [
                'psr-1' => 'psr/psr-1',
                'psr-4' => 'psr/psr-4',
                'psr-7' => 'psr/psr-7',
                'psr-11' => 'psr/psr-11',
                'psr-12' => 'psr/psr-12',
                'psr-15' => 'psr/psr-15',
                'psr-17' => 'psr/psr-17',
                'psr-18' => 'psr/psr-18',
            ],
            metadata: [
                'category' => 'standards',
                'language' => 'php',
            ],
        ));

        // Livewire
        $this->register(new DocumentationSource(
            identifier: 'livewire',
            name: 'Laravel Livewire',
            baseUrl: 'https://livewire.laravel.com/docs',
            endpoints: [
                'quickstart' => 'quickstart',
                'components' => 'components',
                'properties' => 'properties',
                'actions' => 'actions',
                'forms' => 'forms',
                'validation' => 'validation',
                'file-uploads' => 'file-uploads',
                'events' => 'events',
                'lifecycle-hooks' => 'lifecycle-hooks',
                'nesting' => 'nesting',
                'testing' => 'testing',
            ],
            metadata: [
                'category' => 'frontend',
                'language' => 'php',
            ],
        ));

        // Inertia.js
        $this->register(new DocumentationSource(
            identifier: 'inertia',
            name: 'Inertia.js',
            baseUrl: 'https://inertiajs.com',
            endpoints: [
                'installation' => 'installation',
                'pages' => 'pages',
                'responses' => 'responses',
                'redirects' => 'redirects',
                'routing' => 'routing',
                'links' => 'links',
                'forms' => 'forms',
                'file-uploads' => 'file-uploads',
                'validation' => 'validation',
                'shared-data' => 'shared-data',
                'testing' => 'testing',
            ],
            metadata: [
                'category' => 'frontend',
                'language' => 'javascript',
            ],
        ));

        // Tailwind CSS
        $this->register(new DocumentationSource(
            identifier: 'tailwind',
            name: 'Tailwind CSS',
            baseUrl: 'https://tailwindcss.com/docs',
            endpoints: [
                'installation' => 'installation',
                'configuration' => 'configuration',
                'theme' => 'theme',
                'screens' => 'screens',
                'colors' => 'customizing-colors',
                'spacing' => 'customizing-spacing',
                'plugins' => 'plugins',
                'dark-mode' => 'dark-mode',
                'responsive-design' => 'responsive-design',
                'hover-focus' => 'hover-focus-and-other-states',
            ],
            metadata: [
                'category' => 'css',
                'language' => 'css',
            ],
        ));

        // Alpine.js
        $this->register(new DocumentationSource(
            identifier: 'alpine',
            name: 'Alpine.js',
            baseUrl: 'https://alpinejs.dev',
            endpoints: [
                'start' => 'start-here',
                'state' => 'essentials/state',
                'events' => 'essentials/events',
                'templating' => 'essentials/templating',
                'x-data' => 'directives/data',
                'x-bind' => 'directives/bind',
                'x-on' => 'directives/on',
                'x-model' => 'directives/model',
                'x-show' => 'directives/show',
                'x-for' => 'directives/for',
            ],
            metadata: [
                'category' => 'javascript',
                'language' => 'javascript',
            ],
        ));

        // Vue.js
        $this->register(new DocumentationSource(
            identifier: 'vue',
            name: 'Vue.js',
            baseUrl: 'https://vuejs.org/guide',
            endpoints: [
                'introduction' => 'introduction',
                'quick-start' => 'quick-start',
                'reactivity' => 'essentials/reactivity-fundamentals',
                'computed' => 'essentials/computed',
                'components' => 'essentials/component-basics',
                'props' => 'components/props',
                'events' => 'components/events',
                'slots' => 'components/slots',
                'composables' => 'reusability/composables',
                'typescript' => 'typescript/overview',
            ],
            metadata: [
                'category' => 'javascript',
                'language' => 'javascript',
            ],
        ));

        // React
        $this->register(new DocumentationSource(
            identifier: 'react',
            name: 'React',
            baseUrl: 'https://react.dev',
            endpoints: [
                'quick-start' => 'learn',
                'components' => 'learn/your-first-component',
                'props' => 'learn/passing-props-to-a-component',
                'state' => 'learn/state-a-components-memory',
                'effects' => 'learn/synchronizing-with-effects',
                'hooks' => 'reference/react',
                'forms' => 'learn/reacting-to-input-with-state',
            ],
            metadata: [
                'category' => 'javascript',
                'language' => 'javascript',
            ],
        ));

        // Pest PHP
        $this->register(new DocumentationSource(
            identifier: 'pest',
            name: 'Pest PHP',
            baseUrl: 'https://pestphp.com/docs',
            endpoints: [
                'installation' => 'installation',
                'writing-tests' => 'writing-tests',
                'expectations' => 'expectations',
                'hooks' => 'hooks',
                'datasets' => 'datasets',
                'higher-order-tests' => 'higher-order-tests',
                'coverage' => 'coverage',
                'parallel' => 'parallel',
                'arch-testing' => 'arch-testing',
            ],
            metadata: [
                'category' => 'testing',
                'language' => 'php',
            ],
        ));

        // PHPStan
        $this->register(new DocumentationSource(
            identifier: 'phpstan',
            name: 'PHPStan',
            baseUrl: 'https://phpstan.org',
            endpoints: [
                'getting-started' => 'user-guide/getting-started',
                'rule-levels' => 'user-guide/rule-levels',
                'ignoring-errors' => 'user-guide/ignoring-errors',
                'baseline' => 'user-guide/baseline',
                'config-reference' => 'config-reference',
            ],
            metadata: [
                'category' => 'static-analysis',
                'language' => 'php',
            ],
        ));

        // Saloon (HTTP client)
        $this->register(new DocumentationSource(
            identifier: 'saloon',
            name: 'Saloon HTTP Client',
            baseUrl: 'https://docs.saloon.dev',
            endpoints: [
                'introduction' => 'introduction',
                'connectors' => 'the-basics/connectors',
                'requests' => 'the-basics/requests',
                'responses' => 'the-basics/responses',
                'authentication' => 'the-basics/authentication',
                'testing' => 'testing/introduction',
            ],
            metadata: [
                'category' => 'http',
                'language' => 'php',
            ],
        ));

        // Spatie Laravel Packages
        $this->register(new DocumentationSource(
            identifier: 'spatie-permission',
            name: 'Spatie Laravel Permission',
            baseUrl: 'https://spatie.be/docs/laravel-permission/v6',
            endpoints: [
                'installation' => 'installation-laravel',
                'basic-usage' => 'basic-usage/basic-usage',
                'roles' => 'basic-usage/role-permissions',
                'middleware' => 'basic-usage/middleware',
                'blade' => 'basic-usage/blade-directives',
            ],
            metadata: [
                'category' => 'package',
                'language' => 'php',
            ],
        ));

        // GitHub API (for fetching package info)
        $this->register(new DocumentationSource(
            identifier: 'github-api',
            name: 'GitHub API',
            baseUrl: 'https://api.github.com',
            endpoints: [
                'repos' => 'repos/{owner}/{repo}',
                'releases' => 'repos/{owner}/{repo}/releases',
                'latest-release' => 'repos/{owner}/{repo}/releases/latest',
                'readme' => 'repos/{owner}/{repo}/readme',
                'contents' => 'repos/{owner}/{repo}/contents/{path}',
            ],
            metadata: [
                'category' => 'api',
                'type' => 'json',
            ],
        ));

        // Packagist API (for PHP packages)
        $this->register(new DocumentationSource(
            identifier: 'packagist',
            name: 'Packagist API',
            baseUrl: 'https://packagist.org',
            endpoints: [
                'package' => 'packages/{vendor}/{package}.json',
                'search' => 'search.json',
            ],
            metadata: [
                'category' => 'api',
                'type' => 'json',
            ],
        ));

        // NPM Registry (for JS packages)
        $this->register(new DocumentationSource(
            identifier: 'npm',
            name: 'NPM Registry',
            baseUrl: 'https://registry.npmjs.org',
            endpoints: [
                'package' => '{package}',
            ],
            metadata: [
                'category' => 'api',
                'type' => 'json',
            ],
        ));
    }

    /**
     * Create instance.
     */
    public static function create(): self
    {
        return new self;
    }
}
