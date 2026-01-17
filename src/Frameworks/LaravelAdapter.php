<?php

declare(strict_types=1);

namespace LaraForge\Frameworks;

use LaraForge\Core\Contracts\FrameworkAdapterInterface;
use LaraForge\Core\FrameworkDetector;

/**
 * Laravel Framework Adapter
 *
 * Provides Laravel-specific code generation, patterns, and best practices.
 * Supports version-aware recommendations for Laravel 8, 9, 10, 11+.
 */
final class LaravelAdapter implements FrameworkAdapterInterface
{
    public function __construct(
        private readonly FrameworkDetector $detector,
    ) {}

    public function identifier(): string
    {
        return 'laravel';
    }

    public function name(): string
    {
        return 'Laravel';
    }

    public function version(): ?string
    {
        return $this->detector->detect()['version'];
    }

    public function isApplicable(): bool
    {
        return $this->detector->isLaravel();
    }

    public function getDirectoryStructure(): array
    {
        $majorVersion = $this->getMajorVersion();

        $base = [
            'controllers' => 'app/Http/Controllers',
            'models' => 'app/Models',
            'views' => 'resources/views',
            'routes' => 'routes',
            'migrations' => 'database/migrations',
            'seeders' => 'database/seeders',
            'factories' => 'database/factories',
            'tests' => 'tests',
            'config' => 'config',
            'storage' => 'storage',
            'public' => 'public',
        ];

        // Add modern Laravel structure
        if ($majorVersion >= 11) {
            $base['actions'] = 'app/Actions';
            $base['queries'] = 'app/Queries';
            $base['data'] = 'app/Data';
        }

        // Add feature-based structure recommendations
        $base['features'] = [
            'domain' => 'app/Domain/{Feature}',
            'actions' => 'app/Actions/{Feature}',
            'queries' => 'app/Queries/{Feature}',
        ];

        return $base;
    }

    public function getStubTemplates(): array
    {
        $majorVersion = $this->getMajorVersion();

        $stubs = [
            'controller' => 'stubs/laravel/controller.stub',
            'model' => 'stubs/laravel/model.stub',
            'migration' => 'stubs/laravel/migration.stub',
            'seeder' => 'stubs/laravel/seeder.stub',
            'factory' => 'stubs/laravel/factory.stub',
            'request' => 'stubs/laravel/request.stub',
            'resource' => 'stubs/laravel/resource.stub',
            'policy' => 'stubs/laravel/policy.stub',
            'event' => 'stubs/laravel/event.stub',
            'listener' => 'stubs/laravel/listener.stub',
            'job' => 'stubs/laravel/job.stub',
            'mail' => 'stubs/laravel/mail.stub',
            'notification' => 'stubs/laravel/notification.stub',
            'command' => 'stubs/laravel/command.stub',
            'middleware' => 'stubs/laravel/middleware.stub',
            'test' => 'stubs/laravel/test.stub',
            'test-feature' => 'stubs/laravel/test-feature.stub',
        ];

        // Add modern stubs for Laravel 11+
        if ($majorVersion >= 11) {
            $stubs['action'] = 'stubs/laravel/action.stub';
            $stubs['query'] = 'stubs/laravel/query.stub';
            $stubs['data'] = 'stubs/laravel/data.stub';
        }

        // Add Livewire stubs if available
        if ($this->hasFeature('livewire')) {
            $stubs['livewire-component'] = 'stubs/laravel/livewire-component.stub';
            $stubs['livewire-view'] = 'stubs/laravel/livewire-view.stub';
        }

        return $stubs;
    }

    public function getSecurityRules(): array
    {
        return [
            'mass_assignment' => [
                'pattern' => '/protected\s+\$guarded\s*=\s*\[\s*\]/i',
                'message' => 'Empty $guarded allows all attributes. Define $fillable instead.',
                'severity' => 'high',
            ],
            'raw_sql' => [
                'pattern' => '/DB::raw\s*\([^)]*\$/i',
                'message' => 'Variable in DB::raw(). Use parameter binding.',
                'severity' => 'critical',
            ],
            'xss_blade' => [
                'pattern' => '/\{!!\s*\$(?!trusted|safe|html)/i',
                'message' => 'Unescaped Blade output. Only use {!! !!} for trusted HTML.',
                'severity' => 'high',
            ],
            'csrf_missing' => [
                'pattern' => '/<form[^>]*method\s*=\s*["\']post["\'][^>]*>(?!.*@csrf)/is',
                'message' => 'Form without @csrf directive.',
                'severity' => 'high',
            ],
            'auth_bypass' => [
                'pattern' => '/->withoutMiddleware\s*\(\s*[\'"]auth/i',
                'message' => 'Removing auth middleware - ensure this is intentional.',
                'severity' => 'medium',
            ],
            'debug_mode' => [
                'pattern' => '/APP_DEBUG\s*=\s*true/i',
                'message' => 'Debug mode should be false in production.',
                'severity' => 'high',
            ],
            'exposed_env' => [
                'pattern' => '/env\s*\(\s*[\'"](?:DB_PASSWORD|APP_KEY|AWS_SECRET)/i',
                'message' => 'Cache config in production; env() should be in config files.',
                'severity' => 'medium',
            ],
        ];
    }

    public function getDocumentationUrls(): array
    {
        $version = $this->getMajorVersion();

        return [
            'laravel' => "https://laravel.com/docs/{$version}.x",
            'eloquent' => "https://laravel.com/docs/{$version}.x/eloquent",
            'testing' => "https://laravel.com/docs/{$version}.x/testing",
            'validation' => "https://laravel.com/docs/{$version}.x/validation",
            'authorization' => "https://laravel.com/docs/{$version}.x/authorization",
            'queues' => "https://laravel.com/docs/{$version}.x/queues",
            'events' => "https://laravel.com/docs/{$version}.x/events",
            'api_resources' => "https://laravel.com/docs/{$version}.x/eloquent-resources",
            'pest' => 'https://pestphp.com/docs/',
            'livewire' => 'https://livewire.laravel.com/docs/',
            'inertia' => 'https://inertiajs.com/',
        ];
    }

    public function getCodingStandards(): array
    {
        $majorVersion = $this->getMajorVersion();

        return [
            'psr' => [
                'psr-4' => 'Autoloading via Composer',
                'psr-12' => 'Extended Coding Style (use Laravel Pint)',
            ],
            'php' => [
                'strict_types' => true,
                'constructor_promotion' => $majorVersion >= 10,
                'readonly_properties' => $majorVersion >= 10,
                'readonly_classes' => $majorVersion >= 11,
                'enums' => $majorVersion >= 9,
            ],
            'laravel' => [
                'form_requests' => 'Use FormRequest for validation',
                'api_resources' => 'Use Resources for API responses',
                'policies' => 'Use Policies for authorization',
                'events' => 'Use Events for side effects',
                'jobs' => 'Use Jobs for async processing',
                'single_action_controllers' => $majorVersion >= 11,
            ],
            'eloquent' => [
                'fillable' => 'Define $fillable, not empty $guarded',
                'casts' => $majorVersion >= 11 ? 'Use casts() method' : 'Use $casts property',
                'scopes' => 'Use scopes for reusable query logic',
                'accessors_mutators' => 'Use Attribute class for accessors/mutators',
            ],
            'naming' => [
                'models' => 'Singular PascalCase (User, Post)',
                'controllers' => 'PascalCase + Controller suffix',
                'requests' => 'PascalCase + Request suffix',
                'resources' => 'PascalCase + Resource suffix',
                'tables' => 'Plural snake_case (users, blog_posts)',
                'columns' => 'snake_case (created_at, user_id)',
            ],
        ];
    }

    public function getTestingConventions(): array
    {
        $hasPest = $this->hasFeature('pest');

        return [
            'framework' => $hasPest ? 'pest' : 'phpunit',
            'directory' => 'tests',
            'structure' => [
                'unit' => 'tests/Unit',
                'feature' => 'tests/Feature',
                'browser' => 'tests/Browser',
            ],
            'patterns' => [
                'database_transactions' => 'Use RefreshDatabase or DatabaseTransactions',
                'factories' => 'Use model factories for test data',
                'assertions' => $hasPest
                    ? 'Use expect() for fluent assertions'
                    : 'Use $this->assert* methods',
                'http_tests' => 'Use get(), post(), etc. for HTTP tests',
                'mocking' => 'Use Mockery or Laravel fakes',
            ],
            'best_practices' => [
                'Test behavior, not implementation',
                'One assertion per test when possible',
                'Use descriptive test names',
                'Test edge cases and error paths',
                'Use factories for consistent test data',
            ],
        ];
    }

    public function resolvePath(string $component, string $name): string
    {
        $structure = $this->getDirectoryStructure();

        return match ($component) {
            'controller' => $structure['controllers']."/{$name}Controller.php",
            'model' => $structure['models']."/{$name}.php",
            'migration' => $structure['migrations'].'/'.date('Y_m_d_His').'_create_'.strtolower($name).'_table.php',
            'seeder' => $structure['seeders']."/{$name}Seeder.php",
            'factory' => $structure['factories']."/{$name}Factory.php",
            'request' => "app/Http/Requests/{$name}Request.php",
            'resource' => "app/Http/Resources/{$name}Resource.php",
            'policy' => "app/Policies/{$name}Policy.php",
            'event' => "app/Events/{$name}.php",
            'listener' => "app/Listeners/{$name}Listener.php",
            'job' => "app/Jobs/{$name}Job.php",
            'action' => "app/Actions/{$name}Action.php",
            'query' => "app/Queries/{$name}Query.php",
            'test' => "tests/Unit/{$name}Test.php",
            'test-feature' => "tests/Feature/{$name}Test.php",
            'livewire' => "app/Livewire/{$name}.php",
            'view' => $structure['views'].'/'.strtolower(str_replace('\\', '/', $name)).'.blade.php',
            default => "app/{$name}.php",
        };
    }

    public function getAiContext(): array
    {
        $majorVersion = $this->getMajorVersion();
        $features = $this->detector->detect()['features'];

        return [
            'framework' => 'Laravel',
            'version' => $this->version(),
            'major_version' => $majorVersion,
            'features' => $features,
            'patterns' => $this->getPatternRecommendations($majorVersion),
            'avoid' => $this->getAntiPatterns($majorVersion),
            'commands' => [
                'artisan make:*' => 'Generate Laravel components',
                'artisan migrate' => 'Run database migrations',
                'artisan test' => 'Run tests',
                'artisan tinker' => 'Interactive REPL',
            ],
            'tools' => [
                'Laravel Pint' => 'Code formatting',
                'PHPStan with Larastan' => 'Static analysis',
                'Pest' => 'Testing framework',
                'Laravel Telescope' => 'Debugging in development',
            ],
        ];
    }

    /**
     * Check if a specific Laravel feature is available.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->detector->detect()['features'];

        return $features[$feature] ?? false;
    }

    /**
     * Get the major Laravel version.
     */
    private function getMajorVersion(): int
    {
        $version = $this->version();

        if ($version === null) {
            return 11; // Default to latest
        }

        return (int) explode('.', $version)[0];
    }

    /**
     * @return array<string>
     */
    private function getPatternRecommendations(int $majorVersion): array
    {
        $patterns = [
            'Use FormRequest classes for validation',
            'Use API Resources for JSON responses',
            'Use Policies for authorization',
            'Use Events for decoupling',
            'Use Jobs for async processing',
            'Use Eloquent scopes for query logic',
            'Use Factories for test data',
        ];

        if ($majorVersion >= 11) {
            $patterns[] = 'Use single-action controllers';
            $patterns[] = 'Use casts() method instead of $casts property';
            $patterns[] = 'Consider Action classes for business logic';
            $patterns[] = 'Consider Query classes for complex queries';
        }

        if ($majorVersion >= 10) {
            $patterns[] = 'Use constructor property promotion';
            $patterns[] = 'Use readonly properties where applicable';
        }

        return $patterns;
    }

    /**
     * @return array<string>
     */
    private function getAntiPatterns(int $majorVersion): array
    {
        return [
            'Empty $guarded array (mass assignment vulnerability)',
            'Business logic in controllers (use Actions)',
            'Complex queries in controllers (use Query classes)',
            'Raw SQL without parameter binding',
            'Hardcoded configuration values',
            'Using env() outside config files in production',
            'Fat models (extract to traits or services)',
            'N+1 queries (use eager loading)',
        ];
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $path): self
    {
        return new self(
            FrameworkDetector::fromPath($path),
        );
    }
}
