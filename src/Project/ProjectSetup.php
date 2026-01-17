<?php

declare(strict_types=1);

namespace LaraForge\Project;

use LaraForge\Adapters\IdeRegistry;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Project Setup Configuration
 *
 * Handles initial project configuration including:
 * - Application type (API vs Web Application)
 * - Frontend stack selection (Livewire, Inertia, etc.)
 * - IDE support configuration
 * - Modular route organization
 */
final class ProjectSetup
{
    private Filesystem $filesystem;

    public function __construct(
        private readonly string $projectPath,
        private readonly IdeRegistry $ideRegistry,
    ) {
        $this->filesystem = new Filesystem;
    }

    /**
     * Get available application types.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getApplicationTypes(): array
    {
        return [
            'api' => [
                'name' => 'API Only',
                'description' => 'RESTful API with no frontend',
                'features' => [
                    'api_versioning' => 'API versioning support',
                    'sanctum' => 'Laravel Sanctum for authentication',
                    'api_resources' => 'API Resource transformers',
                    'rate_limiting' => 'Rate limiting middleware',
                ],
                'routes' => [
                    'routes/api.php' => 'Main API routes',
                    'routes/api/' => 'Directory for modular API routes',
                ],
            ],
            'web' => [
                'name' => 'Full Web Application',
                'description' => 'Web application with frontend',
                'features' => [
                    'sessions' => 'Session-based authentication',
                    'blade' => 'Blade templating',
                    'assets' => 'Asset compilation with Vite',
                ],
                'frontend_options' => [
                    'blade' => 'Traditional Blade templates',
                    'livewire' => 'Livewire (Full-stack PHP)',
                    'inertia-vue' => 'Inertia.js with Vue',
                    'inertia-react' => 'Inertia.js with React',
                ],
                'routes' => [
                    'routes/web.php' => 'Main web routes',
                    'routes/web/' => 'Directory for modular web routes',
                    'routes/api.php' => 'API routes (optional)',
                ],
            ],
            'hybrid' => [
                'name' => 'Hybrid (Web + API)',
                'description' => 'Full web application with API endpoints',
                'features' => [
                    'sessions' => 'Session-based authentication',
                    'sanctum' => 'Sanctum for API tokens',
                    'spa_auth' => 'SPA authentication support',
                ],
                'routes' => [
                    'routes/web.php' => 'Main web routes',
                    'routes/web/' => 'Directory for modular web routes',
                    'routes/api.php' => 'Main API routes',
                    'routes/api/' => 'Directory for modular API routes',
                ],
            ],
        ];
    }

    /**
     * Get available frontend stacks.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendStacks(): array
    {
        return [
            'blade' => [
                'name' => 'Blade Templates',
                'description' => 'Traditional server-rendered Blade views',
                'packages' => [],
                'npm_packages' => [],
                'pros' => [
                    'Simple and familiar',
                    'Great for content-heavy sites',
                    'SEO-friendly out of the box',
                ],
                'cons' => [
                    'Full page reloads',
                    'Limited interactivity without JavaScript',
                ],
            ],
            'livewire' => [
                'name' => 'Livewire',
                'description' => 'Full-stack framework for dynamic interfaces without JavaScript',
                'packages' => ['livewire/livewire'],
                'npm_packages' => ['alpinejs'],
                'pros' => [
                    'Stay in PHP for interactivity',
                    'Real-time updates',
                    'Great developer experience',
                ],
                'cons' => [
                    'More server load',
                    'Learning curve for complex interactions',
                ],
                'structure' => [
                    'app/Livewire/' => 'Livewire components',
                    'resources/views/livewire/' => 'Livewire views',
                ],
            ],
            'inertia-vue' => [
                'name' => 'Inertia.js + Vue',
                'description' => 'Modern SPA experience with Vue.js',
                'packages' => ['inertiajs/inertia-laravel'],
                'npm_packages' => ['@inertiajs/vue3', 'vue'],
                'pros' => [
                    'SPA-like experience',
                    'Server-side routing',
                    'Great Vue ecosystem',
                ],
                'cons' => [
                    'Requires JavaScript knowledge',
                    'More complex build setup',
                ],
                'structure' => [
                    'resources/js/Pages/' => 'Inertia pages',
                    'resources/js/Components/' => 'Vue components',
                    'resources/js/Layouts/' => 'Layout components',
                ],
            ],
            'inertia-react' => [
                'name' => 'Inertia.js + React',
                'description' => 'Modern SPA experience with React',
                'packages' => ['inertiajs/inertia-laravel'],
                'npm_packages' => ['@inertiajs/react', 'react', 'react-dom'],
                'pros' => [
                    'SPA-like experience',
                    'Server-side routing',
                    'Massive React ecosystem',
                ],
                'cons' => [
                    'Requires JavaScript knowledge',
                    'More complex build setup',
                ],
                'structure' => [
                    'resources/js/Pages/' => 'Inertia pages',
                    'resources/js/Components/' => 'React components',
                    'resources/js/Layouts/' => 'Layout components',
                ],
            ],
        ];
    }

    /**
     * Get modular route configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getModularRouteConfig(): array
    {
        return [
            'web' => [
                'base_file' => 'routes/web.php',
                'module_directory' => 'routes/web',
                'loader_code' => $this->getWebRouteLoaderCode(),
                'example_module' => $this->getExampleWebRouteModule(),
            ],
            'api' => [
                'base_file' => 'routes/api.php',
                'module_directory' => 'routes/api',
                'loader_code' => $this->getApiRouteLoaderCode(),
                'example_module' => $this->getExampleApiRouteModule(),
            ],
        ];
    }

    /**
     * Configure the project with selected options.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function configure(array $config): array
    {
        $results = [
            'files_created' => [],
            'files_modified' => [],
            'packages_to_install' => [],
            'npm_packages_to_install' => [],
            'next_steps' => [],
        ];

        $appType = $config['app_type'] ?? 'web';
        $frontendStack = $config['frontend_stack'] ?? 'blade';
        $ides = $config['ides'] ?? ['claude-code'];
        $modularRoutes = $config['modular_routes'] ?? true;

        // Configure routes
        if ($modularRoutes) {
            $routeResults = $this->setupModularRoutes($appType);
            $results = array_merge_recursive($results, $routeResults);
        }

        // Configure frontend stack
        if ($appType !== 'api') {
            $frontendResults = $this->setupFrontendStack($frontendStack);
            $results = array_merge_recursive($results, $frontendResults);
        }

        // Configure IDE support
        foreach ($ides as $ide) {
            $ideResults = $this->setupIdeSupport($ide);
            $results = array_merge_recursive($results, $ideResults);
        }

        // Create LaraForge configuration
        $configResults = $this->createLaraForgeConfig($config);
        $results = array_merge_recursive($results, $configResults);

        return $results;
    }

    /**
     * Add IDE support to an existing project.
     *
     * @return array<string, mixed>
     */
    public function addIdeSupport(string $identifier): array
    {
        return $this->setupIdeSupport($identifier);
    }

    /**
     * Update IDE configurations.
     *
     * @return array<string, mixed>
     */
    public function updateIdeConfigs(): array
    {
        $updates = $this->ideRegistry->checkForUpdates($this->projectPath);
        $results = [
            'updated' => [],
            'skipped' => [],
        ];

        foreach ($updates as $ide => $files) {
            foreach ($files as $file => $info) {
                $fullPath = $this->projectPath.'/'.$file;
                $content = $this->ideRegistry->generateConfigFiles($ide)[$file] ?? null;

                if ($content && $info['action'] === 'update') {
                    $this->filesystem->dumpFile($fullPath, $content);
                    $results['updated'][] = $file;
                }
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function setupModularRoutes(string $appType): array
    {
        $results = [
            'files_created' => [],
            'files_modified' => [],
        ];

        $config = $this->getModularRouteConfig();

        // Create web routes if needed
        if (\in_array($appType, ['web', 'hybrid'], true)) {
            $webDir = $this->projectPath.'/'.$config['web']['module_directory'];

            if (! $this->filesystem->exists($webDir)) {
                $this->filesystem->mkdir($webDir);
                $results['files_created'][] = $config['web']['module_directory'];
            }

            // Create example module
            $examplePath = $webDir.'/dashboard.php';

            if (! $this->filesystem->exists($examplePath)) {
                $this->filesystem->dumpFile($examplePath, $config['web']['example_module']);
                $results['files_created'][] = 'routes/web/dashboard.php';
            }
        }

        // Create API routes if needed
        if (\in_array($appType, ['api', 'hybrid'], true)) {
            $apiDir = $this->projectPath.'/'.$config['api']['module_directory'];

            if (! $this->filesystem->exists($apiDir)) {
                $this->filesystem->mkdir($apiDir);
                $results['files_created'][] = $config['api']['module_directory'];
            }

            // Create versioned directory structure
            $v1Dir = $apiDir.'/v1';

            if (! $this->filesystem->exists($v1Dir)) {
                $this->filesystem->mkdir($v1Dir);
                $results['files_created'][] = 'routes/api/v1';
            }

            // Create example module
            $examplePath = $v1Dir.'/users.php';

            if (! $this->filesystem->exists($examplePath)) {
                $this->filesystem->dumpFile($examplePath, $config['api']['example_module']);
                $results['files_created'][] = 'routes/api/v1/users.php';
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function setupFrontendStack(string $stack): array
    {
        $stacks = $this->getFrontendStacks();
        $config = $stacks[$stack] ?? null;

        if (! $config) {
            return [];
        }

        $results = [
            'packages_to_install' => $config['packages'] ?? [],
            'npm_packages_to_install' => $config['npm_packages'] ?? [],
            'files_created' => [],
        ];

        // Create directory structure
        if (isset($config['structure'])) {
            foreach ($config['structure'] as $dir => $description) {
                $fullPath = $this->projectPath.'/'.$dir;

                if (! $this->filesystem->exists($fullPath)) {
                    $this->filesystem->mkdir($fullPath);
                    $results['files_created'][] = $dir;
                }
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function setupIdeSupport(string $identifier): array
    {
        $files = $this->ideRegistry->generateConfigFiles($identifier);
        $results = [
            'files_created' => [],
        ];

        foreach ($files as $relativePath => $content) {
            $fullPath = $this->projectPath.'/'.$relativePath;
            $dir = \dirname($fullPath);

            if (! $this->filesystem->exists($dir)) {
                $this->filesystem->mkdir($dir);
            }

            $this->filesystem->dumpFile($fullPath, $content);
            $results['files_created'][] = $relativePath;
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function createLaraForgeConfig(array $config): array
    {
        $configContent = [
            'version' => '1.0.0',
            'app_type' => $config['app_type'] ?? 'web',
            'frontend_stack' => $config['frontend_stack'] ?? 'blade',
            'ides' => $config['ides'] ?? ['claude-code'],
            'modular_routes' => $config['modular_routes'] ?? true,
            'features' => [
                'security_scanning' => true,
                'code_quality' => true,
                'test_generation' => true,
            ],
            'workflows' => [
                'feature' => true,
                'bugfix' => true,
                'refactor' => true,
            ],
        ];

        $path = $this->projectPath.'/laraforge.yaml';
        $this->filesystem->dumpFile(
            $path,
            "# LaraForge Configuration\n".\Symfony\Component\Yaml\Yaml::dump($configContent, 4),
        );

        return [
            'files_created' => ['laraforge.yaml'],
        ];
    }

    private function getWebRouteLoaderCode(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modular Web Routes Loader
|--------------------------------------------------------------------------
|
| This file loads all modular route files from the routes/web directory.
| Each feature/module can have its own route file for better organization.
|
*/

// Load all route files from routes/web directory
$routeFiles = glob(__DIR__ . '/web/*.php');

foreach ($routeFiles as $routeFile) {
    require $routeFile;
}
PHP;
    }

    private function getApiRouteLoaderCode(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modular API Routes Loader
|--------------------------------------------------------------------------
|
| This file loads all modular API route files from the routes/api directory.
| API routes are organized by version (v1, v2) and feature modules.
|
*/

// Load v1 API routes
Route::prefix('v1')->group(function () {
    $routeFiles = glob(__DIR__ . '/api/v1/*.php');

    foreach ($routeFiles as $routeFile) {
        require $routeFile;
    }
});

// Add additional API versions as needed:
// Route::prefix('v2')->group(function () {
//     $routeFiles = glob(__DIR__ . '/api/v2/*.php');
//     foreach ($routeFiles as $routeFile) {
//         require $routeFile;
//     }
// });
PHP;
    }

    private function getExampleWebRouteModule(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Routes for the dashboard module.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Add more dashboard-related routes here
});
PHP;
    }

    private function getExampleApiRouteModule(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Users API Routes (v1)
|--------------------------------------------------------------------------
|
| API routes for user management.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/users', [App\Http\Controllers\Api\V1\UserController::class, 'index']);
    Route::get('/users/{user}', [App\Http\Controllers\Api\V1\UserController::class, 'show']);
    Route::post('/users', [App\Http\Controllers\Api\V1\UserController::class, 'store']);
    Route::put('/users/{user}', [App\Http\Controllers\Api\V1\UserController::class, 'update']);
    Route::delete('/users/{user}', [App\Http\Controllers\Api\V1\UserController::class, 'destroy']);
});
PHP;
    }
}
