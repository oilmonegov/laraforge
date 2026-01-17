<?php

declare(strict_types=1);

namespace LaraForge\Project;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Interaction Context
 *
 * Manages the interaction mode for skills, commands, and agents.
 * Determines when to ask questions vs. proceed autonomously based on
 * established project configuration and guidelines.
 */
final class InteractionContext
{
    /**
     * Interaction modes.
     */
    public const MODE_INTERACTIVE = 'interactive';

    public const MODE_AUTONOMOUS = 'autonomous';

    public const MODE_GUIDED = 'guided';

    /**
     * Decision categories that can be established.
     */
    private const DECISION_CATEGORIES = [
        'stack' => [
            'description' => 'Technology stack choices',
            'questions' => ['framework', 'frontend', 'database', 'cache', 'queue'],
        ],
        'structure' => [
            'description' => 'Project structure decisions',
            'questions' => ['architecture', 'patterns', 'directory_layout'],
        ],
        'styling' => [
            'description' => 'UI and styling choices',
            'questions' => ['css_framework', 'component_library', 'design_system'],
        ],
        'testing' => [
            'description' => 'Testing approach',
            'questions' => ['test_framework', 'coverage_threshold', 'test_types'],
        ],
        'deployment' => [
            'description' => 'Deployment configuration',
            'questions' => ['environment', 'ci_cd', 'hosting'],
        ],
        'security' => [
            'description' => 'Security settings',
            'questions' => ['authentication', 'authorization', 'encryption'],
        ],
        'storage' => [
            'description' => 'File storage configuration',
            'questions' => ['storage_driver', 'cdn', 'upload_categories'],
        ],
    ];

    private Filesystem $filesystem;

    /**
     * @var array<string, mixed>
     */
    private array $establishedDecisions = [];

    public function __construct(
        private readonly string $projectPath,
        private string $mode = self::MODE_GUIDED,
    ) {
        $this->filesystem = new Filesystem;
        $this->loadEstablishedDecisions();
    }

    /**
     * Check if a decision has been established.
     */
    public function isEstablished(string $category, ?string $decision = null): bool
    {
        if ($decision === null) {
            return isset($this->establishedDecisions[$category])
                && $this->establishedDecisions[$category] !== [];
        }

        return isset($this->establishedDecisions[$category][$decision]);
    }

    /**
     * Get an established decision value.
     */
    public function getDecision(string $category, string $decision, mixed $default = null): mixed
    {
        return $this->establishedDecisions[$category][$decision] ?? $default;
    }

    /**
     * Establish a decision.
     */
    public function establish(string $category, string $decision, mixed $value): void
    {
        if (! isset($this->establishedDecisions[$category])) {
            $this->establishedDecisions[$category] = [];
        }

        $this->establishedDecisions[$category][$decision] = $value;
        $this->saveEstablishedDecisions();
    }

    /**
     * Establish multiple decisions at once.
     *
     * @param  array<string, mixed>  $decisions
     */
    public function establishBatch(string $category, array $decisions): void
    {
        foreach ($decisions as $decision => $value) {
            $this->establish($category, $decision, $value);
        }
    }

    /**
     * Check if a question should be asked (not yet established).
     */
    public function shouldAsk(string $category, string $question): bool
    {
        // Always ask in interactive mode unless explicitly established
        if ($this->mode === self::MODE_INTERACTIVE) {
            return ! $this->isEstablished($category, $question);
        }

        // In autonomous mode, never ask - use defaults
        if ($this->mode === self::MODE_AUTONOMOUS) {
            return false;
        }

        // In guided mode, ask only if not established
        return ! $this->isEstablished($category, $question);
    }

    /**
     * Get questions that need to be asked for a category.
     *
     * @return array<string>
     */
    public function getPendingQuestions(string $category): array
    {
        $categoryConfig = self::DECISION_CATEGORIES[$category] ?? null;

        if ($categoryConfig === null) {
            return [];
        }

        $pending = [];
        foreach ($categoryConfig['questions'] as $question) {
            if ($this->shouldAsk($category, $question)) {
                $pending[] = $question;
            }
        }

        return $pending;
    }

    /**
     * Get all pending questions across categories.
     *
     * @return array<string, array<string>>
     */
    public function getAllPendingQuestions(): array
    {
        $pending = [];

        foreach (array_keys(self::DECISION_CATEGORIES) as $category) {
            $categoryPending = $this->getPendingQuestions($category);
            if ($categoryPending !== []) {
                $pending[$category] = $categoryPending;
            }
        }

        return $pending;
    }

    /**
     * Check if any questions are pending.
     */
    public function hasPendingQuestions(): bool
    {
        return $this->getAllPendingQuestions() !== [];
    }

    /**
     * Get the current interaction mode.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Set the interaction mode.
     */
    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Check if operating in interactive mode.
     */
    public function isInteractive(): bool
    {
        return $this->mode === self::MODE_INTERACTIVE;
    }

    /**
     * Check if operating in autonomous mode.
     */
    public function isAutonomous(): bool
    {
        return $this->mode === self::MODE_AUTONOMOUS;
    }

    /**
     * Get configuration completeness as a percentage.
     */
    public function getCompletenessScore(): float
    {
        $totalQuestions = 0;
        $answeredQuestions = 0;

        foreach (self::DECISION_CATEGORIES as $category => $config) {
            foreach ($config['questions'] as $question) {
                $totalQuestions++;
                if ($this->isEstablished($category, $question)) {
                    $answeredQuestions++;
                }
            }
        }

        // DECISION_CATEGORIES is non-empty, so $totalQuestions is always > 0
        return round(($answeredQuestions / $totalQuestions) * 100, 1);
    }

    /**
     * Get all established decisions.
     *
     * @return array<string, mixed>
     */
    public function getAllDecisions(): array
    {
        return $this->establishedDecisions;
    }

    /**
     * Get available decision categories.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCategories(): array
    {
        return self::DECISION_CATEGORIES;
    }

    /**
     * Get question definitions for a category.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getQuestionDefinitions(string $category): array
    {
        return match ($category) {
            'stack' => $this->getStackQuestions(),
            'structure' => $this->getStructureQuestions(),
            'styling' => $this->getStylingQuestions(),
            'testing' => $this->getTestingQuestions(),
            'deployment' => $this->getDeploymentQuestions(),
            'security' => $this->getSecurityQuestions(),
            'storage' => $this->getStorageQuestions(),
            default => [],
        };
    }

    /**
     * Determine if an operation should proceed or wait for user input.
     *
     * @param  array<string>  $requiredDecisions
     */
    public function canProceed(string $category, array $requiredDecisions): bool
    {
        foreach ($requiredDecisions as $decision) {
            if (! $this->isEstablished($category, $decision)) {
                return $this->isAutonomous(); // Only proceed in autonomous mode
            }
        }

        return true;
    }

    /**
     * Get default value for a decision.
     */
    public function getDefault(string $category, string $decision): mixed
    {
        $questions = $this->getQuestionDefinitions($category);

        return $questions[$decision]['default'] ?? null;
    }

    /**
     * Get or establish with default.
     */
    public function getOrDefault(string $category, string $decision): mixed
    {
        if ($this->isEstablished($category, $decision)) {
            return $this->getDecision($category, $decision);
        }

        $default = $this->getDefault($category, $decision);

        if ($this->isAutonomous() && $default !== null) {
            $this->establish($category, $decision, $default);
        }

        return $default;
    }

    /**
     * Reset all established decisions.
     */
    public function reset(): void
    {
        $this->establishedDecisions = [];
        $this->saveEstablishedDecisions();
    }

    /**
     * Reset a specific category.
     */
    public function resetCategory(string $category): void
    {
        unset($this->establishedDecisions[$category]);
        $this->saveEstablishedDecisions();
    }

    /**
     * Export configuration as array.
     *
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return [
            'mode' => $this->mode,
            'completeness' => $this->getCompletenessScore(),
            'decisions' => $this->establishedDecisions,
        ];
    }

    /**
     * Import configuration from array.
     *
     * @param  array<string, mixed>  $config
     */
    public function import(array $config): void
    {
        if (isset($config['mode'])) {
            $this->mode = $config['mode'];
        }

        if (isset($config['decisions'])) {
            $this->establishedDecisions = $config['decisions'];
            $this->saveEstablishedDecisions();
        }
    }

    private function loadEstablishedDecisions(): void
    {
        $configPath = $this->projectPath.'/.laraforge/decisions.json';

        if ($this->filesystem->exists($configPath)) {
            $content = file_get_contents($configPath);
            if ($content !== false) {
                $this->establishedDecisions = json_decode($content, true) ?? [];
            }
        }
    }

    private function saveEstablishedDecisions(): void
    {
        $configDir = $this->projectPath.'/.laraforge';
        $configPath = $configDir.'/decisions.json';

        if (! $this->filesystem->exists($configDir)) {
            $this->filesystem->mkdir($configDir);
        }

        file_put_contents(
            $configPath,
            json_encode($this->establishedDecisions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getStackQuestions(): array
    {
        return [
            'framework' => [
                'question' => 'Which PHP framework are you using?',
                'type' => 'select',
                'options' => ['laravel', 'symfony', 'slim', 'generic'],
                'default' => 'laravel',
            ],
            'frontend' => [
                'question' => 'What frontend stack would you like to use?',
                'type' => 'select',
                'options' => ['blade', 'livewire', 'inertia-vue', 'inertia-react', 'api-only'],
                'default' => 'blade',
            ],
            'database' => [
                'question' => 'Which database will you use?',
                'type' => 'select',
                'options' => ['mysql', 'postgresql', 'sqlite', 'sqlserver'],
                'default' => 'mysql',
            ],
            'cache' => [
                'question' => 'Which cache driver will you use?',
                'type' => 'select',
                'options' => ['redis', 'memcached', 'file', 'database'],
                'default' => 'redis',
            ],
            'queue' => [
                'question' => 'Which queue driver will you use?',
                'type' => 'select',
                'options' => ['redis', 'database', 'sqs', 'sync'],
                'default' => 'redis',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getStructureQuestions(): array
    {
        return [
            'architecture' => [
                'question' => 'Which architecture pattern do you prefer?',
                'type' => 'select',
                'options' => ['mvc', 'domain-driven', 'modular', 'action-based'],
                'default' => 'mvc',
            ],
            'patterns' => [
                'question' => 'Which patterns should be used?',
                'type' => 'multiselect',
                'options' => ['repository', 'service', 'action', 'query', 'dto'],
                'default' => ['service', 'action'],
            ],
            'directory_layout' => [
                'question' => 'Directory layout preference?',
                'type' => 'select',
                'options' => ['standard', 'feature-based', 'domain-based'],
                'default' => 'standard',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getStylingQuestions(): array
    {
        return [
            'css_framework' => [
                'question' => 'Which CSS framework will you use?',
                'type' => 'select',
                'options' => ['tailwind', 'bootstrap', 'bulma', 'custom'],
                'default' => 'tailwind',
            ],
            'component_library' => [
                'question' => 'Which component library?',
                'type' => 'select',
                'options' => ['shadcn', 'headlessui', 'daisyui', 'flowbite', 'custom'],
                'default' => 'shadcn',
            ],
            'design_system' => [
                'question' => 'Do you have existing brand guidelines?',
                'type' => 'boolean',
                'default' => false,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getTestingQuestions(): array
    {
        return [
            'test_framework' => [
                'question' => 'Which testing framework?',
                'type' => 'select',
                'options' => ['pest', 'phpunit'],
                'default' => 'pest',
            ],
            'coverage_threshold' => [
                'question' => 'Minimum code coverage percentage?',
                'type' => 'number',
                'default' => 80,
            ],
            'test_types' => [
                'question' => 'Which test types to include?',
                'type' => 'multiselect',
                'options' => ['unit', 'feature', 'integration', 'browser'],
                'default' => ['unit', 'feature'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getDeploymentQuestions(): array
    {
        return [
            'environment' => [
                'question' => 'Target deployment environment?',
                'type' => 'select',
                'options' => ['traditional', 'docker', 'serverless', 'kubernetes'],
                'default' => 'docker',
            ],
            'ci_cd' => [
                'question' => 'CI/CD platform?',
                'type' => 'select',
                'options' => ['github-actions', 'gitlab-ci', 'bitbucket', 'none'],
                'default' => 'github-actions',
            ],
            'hosting' => [
                'question' => 'Hosting provider?',
                'type' => 'select',
                'options' => ['aws', 'digitalocean', 'forge', 'vapor', 'custom'],
                'default' => 'forge',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getSecurityQuestions(): array
    {
        return [
            'authentication' => [
                'question' => 'Authentication method?',
                'type' => 'select',
                'options' => ['sanctum', 'passport', 'jwt', 'session'],
                'default' => 'sanctum',
            ],
            'authorization' => [
                'question' => 'Authorization approach?',
                'type' => 'select',
                'options' => ['policies', 'gates', 'spatie-permission', 'custom'],
                'default' => 'policies',
            ],
            'encryption' => [
                'question' => 'Additional encryption for sensitive data?',
                'type' => 'boolean',
                'default' => true,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getStorageQuestions(): array
    {
        return [
            'storage_driver' => [
                'question' => 'File storage driver?',
                'type' => 'select',
                'options' => ['s3', 'digitalocean', 'cloudflare-r2', 'local'],
                'default' => 's3',
            ],
            'cdn' => [
                'question' => 'Use CDN for public files?',
                'type' => 'boolean',
                'default' => true,
            ],
            'upload_categories' => [
                'question' => 'Which upload categories to configure?',
                'type' => 'multiselect',
                'options' => ['public', 'private', 'avatars', 'documents', 'signatures'],
                'default' => ['public', 'private', 'avatars'],
            ],
        ];
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $path): self
    {
        return new self($path);
    }
}
