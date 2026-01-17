<?php

declare(strict_types=1);

namespace LaraForge\Project;

use LaraForge\Adapters\IdeRegistry;

/**
 * Interactive Project Setup
 *
 * Provides an interactive setup experience similar to Laravel's installer.
 * Asks users questions and reviews configuration before executing.
 */
final class InteractiveSetup
{
    /**
     * @var array<string, mixed>
     */
    private array $answers = [];

    /**
     * @var array<string, mixed>
     */
    private array $defaults = [];

    public function __construct(
        private readonly ProjectSetup $projectSetup,
        private readonly IdeRegistry $ideRegistry,
        private readonly ?TechnologyStack $stack = null,
    ) {
        $this->loadDefaults();
    }

    /**
     * Get all setup questions in order.
     *
     * @return array<array<string, mixed>>
     */
    public function getQuestions(): array
    {
        return [
            [
                'id' => 'app_type',
                'question' => 'What type of application are you building?',
                'type' => 'choice',
                'options' => $this->getAppTypeOptions(),
                'default' => $this->defaults['app_type'] ?? 'web',
                'help' => 'Choose based on whether you need a frontend or API-only',
            ],
            [
                'id' => 'frontend_stack',
                'question' => 'Which frontend stack would you like to use?',
                'type' => 'choice',
                'options' => $this->getFrontendStackOptions(),
                'default' => $this->defaults['frontend_stack'] ?? 'livewire',
                'depends_on' => ['app_type' => ['web', 'hybrid']],
                'help' => 'Select the technology for building your UI',
            ],
            [
                'id' => 'ides',
                'question' => 'Which IDEs/AI tools will you be using?',
                'type' => 'multiselect',
                'options' => $this->getIdeOptions(),
                'default' => $this->defaults['ides'] ?? ['claude-code'],
                'help' => 'Select all that apply - you can add more later',
            ],
            [
                'id' => 'testing_framework',
                'question' => 'Which testing framework do you prefer?',
                'type' => 'choice',
                'options' => [
                    'pest' => 'Pest (Recommended for Laravel)',
                    'phpunit' => 'PHPUnit (Traditional)',
                ],
                'default' => $this->defaults['testing_framework'] ?? 'pest',
                'help' => 'Pest provides a more elegant syntax and better DX',
            ],
            [
                'id' => 'modular_routes',
                'question' => 'Would you like modular route organization?',
                'type' => 'confirm',
                'default' => $this->defaults['modular_routes'] ?? true,
                'help' => 'Organizes routes into separate files per feature/module',
            ],
            [
                'id' => 'api_versioning',
                'question' => 'Would you like API versioning support?',
                'type' => 'confirm',
                'default' => $this->defaults['api_versioning'] ?? true,
                'depends_on' => ['app_type' => ['api', 'hybrid']],
                'help' => 'Enables v1, v2, etc. API versioning structure',
            ],
            [
                'id' => 'database_driver',
                'question' => 'Which database will you primarily use?',
                'type' => 'choice',
                'options' => [
                    'mysql' => 'MySQL',
                    'pgsql' => 'PostgreSQL',
                    'sqlite' => 'SQLite (Development/Testing)',
                    'sqlsrv' => 'SQL Server',
                ],
                'default' => $this->detectDatabaseDriver(),
                'help' => 'Choose your production database - code will be portable',
            ],
            [
                'id' => 'auth_type',
                'question' => 'What authentication method do you need?',
                'type' => 'choice',
                'options' => $this->getAuthOptions(),
                'default' => $this->defaults['auth_type'] ?? 'sanctum',
                'help' => 'Select based on your client types (SPA, mobile, API)',
            ],
            [
                'id' => 'enable_security_scanning',
                'question' => 'Enable security scanning for generated code?',
                'type' => 'confirm',
                'default' => true,
                'help' => 'Validates generated code against OWASP Top 10',
            ],
            [
                'id' => 'enable_rector',
                'question' => 'Would you like Rector for automated refactoring?',
                'type' => 'confirm',
                'default' => true,
                'help' => 'Keeps code up-to-date with latest PHP practices',
            ],
        ];
    }

    /**
     * Record an answer to a question.
     */
    public function answer(string $questionId, mixed $value): void
    {
        $this->answers[$questionId] = $value;
    }

    /**
     * Get all recorded answers.
     *
     * @return array<string, mixed>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /**
     * Check if a question should be asked based on dependencies.
     */
    public function shouldAskQuestion(array $question): bool
    {
        if (! isset($question['depends_on'])) {
            return true;
        }

        foreach ($question['depends_on'] as $field => $allowedValues) {
            $currentValue = $this->answers[$field] ?? null;

            if (! \in_array($currentValue, $allowedValues, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the default value for a question.
     */
    public function getDefault(string $questionId): mixed
    {
        $questions = $this->getQuestions();

        foreach ($questions as $question) {
            if ($question['id'] === $questionId) {
                return $question['default'] ?? null;
            }
        }

        return null;
    }

    /**
     * Generate a configuration review for user confirmation.
     *
     * @return array<string, array<string, mixed>>
     */
    public function generateReview(): array
    {
        $config = $this->buildConfiguration();

        return [
            'summary' => [
                'Application Type' => $this->formatAppType($config['app_type'] ?? 'web'),
                'Frontend Stack' => $this->formatFrontendStack($config['frontend_stack'] ?? 'blade'),
                'IDE Support' => implode(', ', $config['ides'] ?? ['claude-code']),
                'Testing' => $config['testing_framework'] ?? 'pest',
                'Database' => $config['database_driver'] ?? 'mysql',
                'Authentication' => $config['auth_type'] ?? 'sanctum',
            ],
            'features' => [
                'Modular Routes' => $config['modular_routes'] ?? true ? 'Yes' : 'No',
                'API Versioning' => $config['api_versioning'] ?? false ? 'Yes' : 'No',
                'Security Scanning' => $config['enable_security_scanning'] ?? true ? 'Yes' : 'No',
                'Rector Refactoring' => $config['enable_rector'] ?? true ? 'Yes' : 'No',
            ],
            'files_to_create' => $this->getFilesToCreate($config),
            'packages_to_install' => $this->getPackagesToInstall($config),
            'npm_packages' => $this->getNpmPackages($config),
        ];
    }

    /**
     * Build the final configuration from answers.
     *
     * @return array<string, mixed>
     */
    public function buildConfiguration(): array
    {
        $config = [];

        foreach ($this->getQuestions() as $question) {
            $id = $question['id'];
            $config[$id] = $this->answers[$id] ?? $question['default'] ?? null;
        }

        return $config;
    }

    /**
     * Execute the setup with the configured options.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return $this->projectSetup->configure($this->buildConfiguration());
    }

    /**
     * Load defaults from PRD or existing configuration.
     */
    private function loadDefaults(): void
    {
        // Try to detect from technology stack
        if ($this->stack) {
            $detected = $this->stack->detect();

            $this->defaults['database_driver'] = $detected['database']['default'] ?? 'mysql';
            $this->defaults['testing_framework'] = $detected['testing']['framework'] ?? 'pest';

            if ($detected['frontend']['livewire']) {
                $this->defaults['frontend_stack'] = 'livewire';
                $this->defaults['app_type'] = 'web';
            } elseif ($detected['frontend']['inertia']) {
                $this->defaults['frontend_stack'] = $detected['frontend']['vue'] ? 'inertia-vue' : 'inertia-react';
                $this->defaults['app_type'] = 'web';
            }

            if ($detected['api']['sanctum']) {
                $this->defaults['auth_type'] = 'sanctum';
            } elseif ($detected['api']['passport']) {
                $this->defaults['auth_type'] = 'passport';
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function getAppTypeOptions(): array
    {
        $types = $this->projectSetup->getApplicationTypes();
        $options = [];

        foreach ($types as $key => $config) {
            $options[$key] = $config['name'].' - '.$config['description'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function getFrontendStackOptions(): array
    {
        $stacks = $this->projectSetup->getFrontendStacks();
        $options = [];

        foreach ($stacks as $key => $config) {
            $options[$key] = $config['name'].' - '.$config['description'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function getIdeOptions(): array
    {
        $options = [];
        $metadata = $this->ideRegistry->metadata();

        foreach ($metadata as $id => $config) {
            $status = $config['available'] ? '✓' : '';
            $options[$id] = $config['name'].($status ? " ({$status} detected)" : '');
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function getAuthOptions(): array
    {
        return [
            'sanctum' => 'Laravel Sanctum (API tokens + SPA)',
            'passport' => 'Laravel Passport (Full OAuth2)',
            'breeze' => 'Laravel Breeze (Simple auth scaffold)',
            'fortify' => 'Laravel Fortify (Headless auth)',
            'none' => 'No authentication (configure later)',
        ];
    }

    private function detectDatabaseDriver(): string
    {
        if ($this->stack) {
            return $this->stack->detect()['database']['default'] ?? 'mysql';
        }

        return 'mysql';
    }

    private function formatAppType(string $type): string
    {
        $types = $this->projectSetup->getApplicationTypes();

        return $types[$type]['name'] ?? $type;
    }

    private function formatFrontendStack(string $stack): string
    {
        $stacks = $this->projectSetup->getFrontendStacks();

        return $stacks[$stack]['name'] ?? $stack;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string>
     */
    private function getFilesToCreate(array $config): array
    {
        $files = ['laraforge.yaml'];

        // IDE files
        foreach ($config['ides'] ?? [] as $ide) {
            if ($ide === 'claude-code') {
                $files[] = 'CLAUDE.md';
            } elseif ($ide === 'cursor') {
                $files[] = '.cursorrules';
            }
        }

        // Route files
        if ($config['modular_routes'] ?? true) {
            if (\in_array($config['app_type'], ['web', 'hybrid'], true)) {
                $files[] = 'routes/web/';
            }
            if (\in_array($config['app_type'], ['api', 'hybrid'], true)) {
                $files[] = 'routes/api/';
            }
        }

        // Rector config
        if ($config['enable_rector'] ?? true) {
            $files[] = 'rector.php';
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string>
     */
    private function getPackagesToInstall(array $config): array
    {
        $packages = [];

        // Frontend stack
        $stacks = $this->projectSetup->getFrontendStacks();
        $stack = $config['frontend_stack'] ?? 'blade';

        if (isset($stacks[$stack]['packages'])) {
            $packages = array_merge($packages, $stacks[$stack]['packages']);
        }

        // Auth packages
        $auth = $config['auth_type'] ?? 'none';

        if ($auth !== 'none') {
            $packages[] = "laravel/{$auth}";
        }

        // Testing
        if (($config['testing_framework'] ?? 'pest') === 'pest') {
            $packages[] = 'pestphp/pest';
            $packages[] = 'pestphp/pest-plugin-laravel';
        }

        // Rector
        if ($config['enable_rector'] ?? true) {
            $packages[] = 'rector/rector';
        }

        return array_unique($packages);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string>
     */
    private function getNpmPackages(array $config): array
    {
        $packages = [];

        $stacks = $this->projectSetup->getFrontendStacks();
        $stack = $config['frontend_stack'] ?? 'blade';

        if (isset($stacks[$stack]['npm_packages'])) {
            $packages = array_merge($packages, $stacks[$stack]['npm_packages']);
        }

        return array_unique($packages);
    }

    /**
     * Generate a CLI-friendly review string.
     */
    public function generateReviewText(): string
    {
        $review = $this->generateReview();

        $text = "\n╔══════════════════════════════════════════════════════════════╗\n";
        $text .= "║                    PROJECT CONFIGURATION                     ║\n";
        $text .= "╠══════════════════════════════════════════════════════════════╣\n";

        foreach ($review['summary'] as $key => $value) {
            $text .= sprintf("║  %-20s: %-37s ║\n", $key, $value);
        }

        $text .= "╠══════════════════════════════════════════════════════════════╣\n";
        $text .= "║  Features                                                    ║\n";
        $text .= "╠══════════════════════════════════════════════════════════════╣\n";

        foreach ($review['features'] as $key => $value) {
            $text .= sprintf("║  %-20s: %-37s ║\n", $key, $value);
        }

        if (! empty($review['files_to_create'])) {
            $text .= "╠══════════════════════════════════════════════════════════════╣\n";
            $text .= "║  Files to Create                                             ║\n";
            $text .= "╠══════════════════════════════════════════════════════════════╣\n";

            foreach ($review['files_to_create'] as $file) {
                $text .= sprintf("║    • %-55s ║\n", $file);
            }
        }

        if (! empty($review['packages_to_install'])) {
            $text .= "╠══════════════════════════════════════════════════════════════╣\n";
            $text .= "║  Packages to Install                                         ║\n";
            $text .= "╠══════════════════════════════════════════════════════════════╣\n";

            foreach ($review['packages_to_install'] as $package) {
                $text .= sprintf("║    • %-55s ║\n", $package);
            }
        }

        $text .= "╚══════════════════════════════════════════════════════════════╝\n";

        return $text;
    }
}
