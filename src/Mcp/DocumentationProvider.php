<?php

declare(strict_types=1);

namespace LaraForge\Mcp;

use LaraForge\Project\TechnologyStack;

/**
 * MCP Documentation Provider
 *
 * Provides version-aware documentation queries using MCP (Model Context Protocol).
 * This class serves as the bridge between LaraForge and Laravel MCP server
 * for fetching best practices and documentation.
 *
 * Usage with Claude Code / AI Agents:
 * - Automatically detects project's technology stack
 * - Builds version-specific documentation URLs
 * - Provides context for AI prompts
 * - Ensures recommendations match the project's actual versions
 */
final class DocumentationProvider
{
    /**
     * Documentation sources with version placeholders.
     *
     * @var array<string, array{base_url: string, version_format: string, paths: array<string, string>}>
     */
    private const SOURCES = [
        'laravel' => [
            'base_url' => 'https://laravel.com/docs/{version}',
            'version_format' => '{major}.x',
            'paths' => [
                'installation' => '/installation',
                'configuration' => '/configuration',
                'routing' => '/routing',
                'controllers' => '/controllers',
                'requests' => '/requests',
                'responses' => '/responses',
                'views' => '/views',
                'blade' => '/blade',
                'validation' => '/validation',
                'errors' => '/errors',
                'logging' => '/logging',
                'artisan' => '/artisan',
                'broadcasting' => '/broadcasting',
                'cache' => '/cache',
                'collections' => '/collections',
                'contracts' => '/contracts',
                'events' => '/events',
                'filesystem' => '/filesystem',
                'helpers' => '/helpers',
                'http-client' => '/http-client',
                'localization' => '/localization',
                'mail' => '/mail',
                'notifications' => '/notifications',
                'packages' => '/packages',
                'queues' => '/queues',
                'rate-limiting' => '/rate-limiting',
                'scheduling' => '/scheduling',
                'database' => '/database',
                'eloquent' => '/eloquent',
                'migrations' => '/migrations',
                'seeding' => '/seeding',
                'redis' => '/redis',
                'testing' => '/testing',
                'http-tests' => '/http-tests',
                'console-tests' => '/console-tests',
                'browser-tests' => '/dusk',
                'mocking' => '/mocking',
                'sanctum' => '/sanctum',
                'passport' => '/passport',
                'socialite' => '/socialite',
                'telescope' => '/telescope',
                'horizon' => '/horizon',
                'fortify' => '/fortify',
                'pennant' => '/pennant',
                'prompts' => '/prompts',
                'pulse' => '/pulse',
                'reverb' => '/reverb',
            ],
        ],
        'pest' => [
            'base_url' => 'https://pestphp.com/docs',
            'version_format' => '',
            'paths' => [
                'installation' => '/installation',
                'writing-tests' => '/writing-tests',
                'expectations' => '/expectations',
                'hooks' => '/hooks',
                'datasets' => '/datasets',
                'higher-order-tests' => '/higher-order-tests',
                'custom-expectations' => '/custom-expectations',
                'arch-testing' => '/arch-testing',
                'stress-testing' => '/stress-testing',
                'snapshot-testing' => '/snapshot-testing',
                'type-coverage' => '/type-coverage',
                'mutation-testing' => '/mutation-testing',
            ],
        ],
        'livewire' => [
            'base_url' => 'https://livewire.laravel.com/docs',
            'version_format' => '',
            'paths' => [
                'installation' => '/installation',
                'quickstart' => '/quickstart',
                'components' => '/components',
                'properties' => '/properties',
                'actions' => '/actions',
                'forms' => '/forms',
                'validation' => '/validation',
                'file-uploads' => '/file-uploads',
                'events' => '/events',
                'lifecycle-hooks' => '/lifecycle-hooks',
                'nesting' => '/nesting',
                'testing' => '/testing',
            ],
        ],
        'inertia' => [
            'base_url' => 'https://inertiajs.com',
            'version_format' => '',
            'paths' => [
                'introduction' => '/',
                'installation' => '/server-side-setup',
                'routing' => '/routing',
                'responses' => '/responses',
                'redirects' => '/redirects',
                'shared-data' => '/shared-data',
                'forms' => '/forms',
                'file-uploads' => '/file-uploads',
                'validation' => '/validation',
                'authorization' => '/authorization',
                'testing' => '/testing',
            ],
        ],
        'php' => [
            'base_url' => 'https://www.php.net/manual/en',
            'version_format' => '',
            'paths' => [
                'classes' => '/language.oop5.php',
                'interfaces' => '/language.oop5.interfaces.php',
                'traits' => '/language.oop5.traits.php',
                'enums' => '/language.enumerations.php',
                'attributes' => '/language.attributes.php',
                'exceptions' => '/language.exceptions.php',
                'generators' => '/language.generators.php',
                'fibers' => '/class.fiber.php',
            ],
        ],
    ];

    public function __construct(
        private readonly TechnologyStack $stack,
    ) {}

    /**
     * Get documentation URL for a specific topic.
     */
    public function getDocUrl(string $source, string $topic): ?string
    {
        if (! isset(self::SOURCES[$source])) {
            return null;
        }

        $sourceConfig = self::SOURCES[$source];

        if (! isset($sourceConfig['paths'][$topic])) {
            return null;
        }

        $version = $this->getVersionForSource($source);
        $baseUrl = str_replace('{version}', $version, $sourceConfig['base_url']);

        return $baseUrl.$sourceConfig['paths'][$topic];
    }

    /**
     * Build MCP query context for documentation lookup.
     *
     * @return array<string, mixed>
     */
    public function buildMcpContext(): array
    {
        $stackInfo = $this->stack->forDocumentationQuery();
        $urls = $this->stack->getDocumentationUrls();

        return [
            'technology_stack' => $stackInfo,
            'documentation_urls' => $urls,
            'laravel_docs_base' => $urls['laravel'] ?? null,
            'context_prompt' => $this->buildContextPrompt(),
            'version_constraints' => [
                'php' => $stackInfo['php_version'],
                'laravel' => $stackInfo['laravel_version'],
                'laravel_major' => $stackInfo['laravel_major'],
            ],
        ];
    }

    /**
     * Build a context prompt for AI interactions.
     */
    public function buildContextPrompt(): string
    {
        $stackContext = $this->stack->toContextString();
        $urls = $this->stack->getDocumentationUrls();

        $urlList = [];
        foreach ($urls as $name => $url) {
            $urlList[] = "- {$name}: {$url}";
        }

        return <<<PROMPT
{$stackContext}

### Documentation References
When providing code examples or recommendations, ensure they are compatible with the versions above.

Available Documentation:
{$this->formatUrlList($urlList)}

### Best Practices Guidelines
1. Always check version compatibility before suggesting features
2. Use Laravel's built-in features before third-party packages
3. Follow Laravel naming conventions and directory structure
4. Prefer Eloquent over raw queries
5. Use Laravel's testing utilities (HTTP tests, database assertions)
6. Follow PSR-12 coding standards
7. Use strict types in all PHP files
PROMPT;
    }

    /**
     * Get relevant documentation URLs for a specific task type.
     *
     * @return array<string, string>
     */
    public function getUrlsForTask(string $taskType): array
    {
        $laravelBase = $this->getDocUrl('laravel', 'installation');
        $laravelBase = dirname((string) $laravelBase); // Remove /installation

        return match ($taskType) {
            'api' => [
                'routing' => "{$laravelBase}/routing",
                'controllers' => "{$laravelBase}/controllers",
                'requests' => "{$laravelBase}/requests",
                'responses' => "{$laravelBase}/responses",
                'validation' => "{$laravelBase}/validation",
                'sanctum' => $this->stack->has('sanctum') ? "{$laravelBase}/sanctum" : null,
            ],
            'model', 'eloquent' => [
                'eloquent' => "{$laravelBase}/eloquent",
                'eloquent-relationships' => "{$laravelBase}/eloquent-relationships",
                'eloquent-collections' => "{$laravelBase}/eloquent-collections",
                'eloquent-mutators' => "{$laravelBase}/eloquent-mutators",
                'migrations' => "{$laravelBase}/migrations",
            ],
            'testing' => array_filter([
                'testing' => "{$laravelBase}/testing",
                'http-tests' => "{$laravelBase}/http-tests",
                'console-tests' => "{$laravelBase}/console-tests",
                'mocking' => "{$laravelBase}/mocking",
                'pest' => $this->stack->has('pest') ? $this->getDocUrl('pest', 'writing-tests') : null,
                'dusk' => $this->stack->has('dusk') ? "{$laravelBase}/dusk" : null,
            ]),
            'frontend' => array_filter([
                'blade' => "{$laravelBase}/blade",
                'livewire' => $this->stack->has('livewire') ? $this->getDocUrl('livewire', 'quickstart') : null,
                'inertia' => $this->stack->has('inertia') ? $this->getDocUrl('inertia', 'introduction') : null,
            ]),
            'queue' => [
                'queues' => "{$laravelBase}/queues",
                'horizon' => $this->stack->has('horizon') ? "{$laravelBase}/horizon" : null,
            ],
            default => [
                'documentation' => $laravelBase,
            ],
        };
    }

    /**
     * Generate stub recommendation based on Laravel version and stub type.
     *
     * @return array<string, mixed>
     */
    public function getStubRecommendations(string $stubType): array
    {
        $laravelVersion = $this->stack->detect()['laravel']['major'] ?? '11';
        $phpVersion = $this->stack->detect()['php']['major'] ?? 8;

        $recommendations = [
            'stub_type' => $stubType,
            'use_attributes' => $phpVersion >= 8,
            'use_constructor_promotion' => $phpVersion >= 8,
            'use_readonly_properties' => $phpVersion >= 8.1,
            'use_enums' => $phpVersion >= 8.1,
            'use_readonly_classes' => $phpVersion >= 8.2,
        ];

        // Laravel version specific recommendations
        $recommendations['laravel'] = match ((int) $laravelVersion) {
            11 => [
                'use_invokable_rules' => true,
                'use_model_casts_method' => true,
                'use_fillable_array' => true,
                'controller_style' => 'single_action_or_resource',
                'form_request' => true,
                'api_resource' => true,
            ],
            10 => [
                'use_invokable_rules' => true,
                'use_model_casts_method' => false,
                'use_fillable_array' => true,
                'controller_style' => 'resource',
                'form_request' => true,
                'api_resource' => true,
            ],
            default => [
                'use_invokable_rules' => false,
                'use_model_casts_method' => false,
                'use_fillable_array' => true,
                'controller_style' => 'resource',
                'form_request' => true,
                'api_resource' => true,
            ],
        };

        // Stub-type specific recommendations
        $recommendations['specific'] = $this->getStubTypeRecommendations($stubType, (int) $laravelVersion);

        return $recommendations;
    }

    /**
     * Get recommendations specific to the stub type.
     *
     * @return array<string, mixed>
     */
    private function getStubTypeRecommendations(string $stubType, int $laravelVersion): array
    {
        return match ($stubType) {
            'controller' => [
                'use_form_request' => true,
                'use_api_resource' => true,
                'use_actions' => true,
                'single_responsibility' => true,
                'thin_controller' => 'Controllers should only handle HTTP, delegate to Actions',
            ],
            'model' => [
                'use_fillable' => true,
                'use_hidden' => ['password', 'remember_token'],
                'use_casts' => $laravelVersion >= 11 ? 'method' : 'property',
                'use_relationships' => true,
                'use_scopes' => 'For reusable query logic',
            ],
            'migration' => [
                'use_foreign_keys' => true,
                'use_indexes' => 'Add for frequently queried columns',
                'use_soft_deletes' => 'Consider for audit trails',
                'database_agnostic' => 'Avoid database-specific features',
            ],
            'request' => [
                'use_authorize' => true,
                'use_rules' => true,
                'use_messages' => 'Custom validation messages',
                'use_attributes' => 'Human-readable attribute names',
            ],
            'action' => [
                'single_responsibility' => true,
                'use_execute_method' => true,
                'use_transactions' => 'Wrap mutations in DB::transaction',
                'return_result_object' => true,
            ],
            'query' => [
                'encapsulate_logic' => true,
                'use_builder_pattern' => true,
                'database_agnostic' => 'Handle driver differences',
                'use_pagination' => 'Always paginate list queries',
            ],
            'test' => [
                'use_pest' => $this->stack->has('pest'),
                'use_factories' => true,
                'use_database_transactions' => true,
                'test_happy_path' => true,
                'test_edge_cases' => true,
            ],
            default => [],
        };
    }

    /**
     * Check if a Laravel feature is available in the detected version.
     */
    public function hasLaravelFeature(string $feature): bool
    {
        $laravelMajor = (int) ($this->stack->detect()['laravel']['major'] ?? 0);

        return match ($feature) {
            'model-casts-method' => $laravelMajor >= 11,
            'invokable-rules' => $laravelMajor >= 9,
            'anonymous-migrations' => $laravelMajor >= 8,
            'model-factory-classes' => $laravelMajor >= 8,
            'job-middleware' => $laravelMajor >= 8,
            'rate-limiter-facade' => $laravelMajor >= 8,
            'lazy-collections' => $laravelMajor >= 6,
            'blade-components' => $laravelMajor >= 7,
            'pennant' => $laravelMajor >= 10,
            'prompts' => $laravelMajor >= 10,
            'pulse' => $laravelMajor >= 10,
            'reverb' => $laravelMajor >= 11,
            default => false,
        };
    }

    private function getVersionForSource(string $source): string
    {
        if ($source === 'laravel') {
            $major = $this->stack->detect()['laravel']['major'] ?? '11';

            return "{$major}.x";
        }

        return '';
    }

    private function formatUrlList(array $urls): string
    {
        return implode("\n", array_filter($urls));
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $projectPath): self
    {
        return new self(TechnologyStack::fromPath($projectPath));
    }
}
