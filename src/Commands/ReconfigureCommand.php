<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Commands\Concerns\SuggestsNextStep;
use LaraForge\LaraForge;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

/**
 * Reconfigure Command
 *
 * Allows users to reconfigure LaraForge settings after initial installation.
 * Supports selective reconfiguration of specific components.
 */
final class ReconfigureCommand extends Command
{
    use SuggestsNextStep;

    private const COMPONENTS = [
        'scale' => 'Project Scale & Architecture',
        'framework' => 'Framework Settings',
        'ai_tools' => 'AI Assistant Integration',
        'testing' => 'Testing Configuration',
        'ci_cd' => 'CI/CD Settings',
        'observability' => 'Observability & Monitoring',
        'frontend' => 'Frontend Stack',
        'security' => 'Security Settings',
    ];

    public function __construct(
        private readonly LaraForge $laraforge,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reconfigure')
            ->setDescription('Reconfigure LaraForge settings')
            ->addOption(
                'component',
                'c',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Specific components to reconfigure'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Reconfigure all settings'
            )
            ->addOption(
                'show',
                's',
                InputOption::VALUE_NONE,
                'Show current configuration without making changes'
            )
            ->addOption(
                'reset',
                null,
                InputOption::VALUE_NONE,
                'Reset to default configuration'
            )
            ->addOption(
                'export',
                null,
                InputOption::VALUE_REQUIRED,
                'Export configuration to file'
            )
            ->addOption(
                'import',
                null,
                InputOption::VALUE_REQUIRED,
                'Import configuration from file'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('LaraForge Reconfiguration');

        // Handle export
        $exportPath = $input->getOption('export');
        if (is_string($exportPath) && $exportPath !== '') {
            return $this->exportConfig($io, $exportPath);
        }

        // Handle import
        $importPath = $input->getOption('import');
        if (is_string($importPath) && $importPath !== '') {
            return $this->importConfig($io, $importPath);
        }

        // Handle show
        if ($input->getOption('show')) {
            return $this->showConfig($io);
        }

        // Handle reset
        if ($input->getOption('reset')) {
            return $this->resetConfig($io);
        }

        // Get components to reconfigure
        /** @var string[] $components */
        $components = $input->getOption('component');

        if ($input->getOption('all')) {
            $components = array_keys(self::COMPONENTS);
        }

        if (empty($components)) {
            $selectedComponents = multiselect(
                label: 'Which components would you like to reconfigure?',
                options: self::COMPONENTS,
                default: [],
                required: true,
            );
            /** @var string[] $components */
            $components = array_values($selectedComponents);
        }

        $config = $this->loadCurrentConfig();

        foreach ($components as $component) {
            if (! isset(self::COMPONENTS[$component])) {
                $io->warning("Unknown component: {$component}");

                continue;
            }

            $io->section(self::COMPONENTS[$component]);
            $config = $this->reconfigureComponent($component, $config, $io);
        }

        // Save configuration
        $this->saveConfig($config);

        $io->success('Configuration updated successfully!');

        // Show what changed
        $io->note('Configuration saved to .laraforge/config.yaml');

        $this->suggestNextSteps($io, 'reconfigure');

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function reconfigureComponent(string $component, array $config, SymfonyStyle $io): array
    {
        return match ($component) {
            'scale' => $this->reconfigureScale($config, $io),
            'framework' => $this->reconfigureFramework($config, $io),
            'ai_tools' => $this->reconfigureAiTools($config, $io),
            'testing' => $this->reconfigureTesting($config, $io),
            'ci_cd' => $this->reconfigureCiCd($config, $io),
            'observability' => $this->reconfigureObservability($config, $io),
            'frontend' => $this->reconfigureFrontend($config, $io),
            'security' => $this->reconfigureSecurity($config, $io),
            default => $config,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function reconfigureScale(array $config, SymfonyStyle $io): array
    {
        $currentTier = $config['scale']['tier'] ?? 'small';

        $tier = select(
            label: 'Select project scale',
            options: [
                'prototype' => 'Prototype (<100 users, simple)',
                'small' => 'Small (100-1K users)',
                'medium' => 'Medium (1K-100K users)',
                'large' => 'Large (100K-1M users)',
                'massive' => 'Massive (1M+ users)',
            ],
            default: $currentTier,
        );

        $config['scale'] = [
            'tier' => $tier,
            'mode' => match ($tier) {
                'prototype', 'small' => 'simple',
                'medium' => 'balanced',
                'large', 'massive' => 'scalable',
                default => 'balanced',
            },
        ];

        $io->text("Scale set to: {$tier}");

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function reconfigureFramework(array $config, SymfonyStyle $io): array
    {
        $current = $config['framework'] ?? 'laravel';

        $framework = select(
            label: 'Select framework',
            options: [
                'laravel' => 'Laravel',
                'symfony' => 'Symfony',
                'generic' => 'Generic PHP',
            ],
            default: $current,
        );

        $config['framework'] = $framework;

        $io->text("Framework set to: {$framework}");

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function reconfigureAiTools(array $config, SymfonyStyle $io): array
    {
        $current = $config['ai_tools'] ?? ['claude'];

        $tools = multiselect(
            label: 'Select AI tools to integrate',
            options: [
                'claude' => 'Claude Code',
                'cursor' => 'Cursor',
                'copilot' => 'GitHub Copilot',
                'generic' => 'Generic AI Assistant',
            ],
            default: $current,
        );

        /** @var string[] $config['ai_tools'] */
        $config['ai_tools'] = array_values($tools);

        $io->text('AI tools: '.implode(', ', $config['ai_tools']));

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function reconfigureTesting(array $config, SymfonyStyle $io): array
    {
        $testing = $config['testing'] ?? [];

        $testing['framework'] = select(
            label: 'Select test framework',
            options: [
                'pest' => 'Pest (Recommended)',
                'phpunit' => 'PHPUnit',
            ],
            default: $testing['framework'] ?? 'pest',
        );

        $testTypes = multiselect(
            label: 'Enable test types',
            options: [
                'unit' => 'Unit Tests',
                'feature' => 'Feature Tests',
                'architecture' => 'Architecture Tests',
                'browser' => 'Browser Tests (Dusk)',
                'property' => 'Property-Based Tests',
                'mutation' => 'Mutation Tests (Infection)',
            ],
            default: $testing['types'] ?? ['unit', 'feature', 'architecture'],
        );

        $testing['types'] = array_values($testTypes);

        $testing['coverage_target'] = (int) ($io->ask(
            'Minimum code coverage percentage',
            (string) ($testing['coverage_target'] ?? 80)
        ) ?? 80);

        $testing['phpstan_level'] = (int) select(
            label: 'PHPStan analysis level',
            options: array_combine(range(1, 9), range(1, 9)),
            default: $testing['phpstan_level'] ?? 8,
        );

        $config['testing'] = $testing;

        $io->text('Testing configured');

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function reconfigureCiCd(array $config, SymfonyStyle $io): array
    {
        $cicd = $config['ci_cd'] ?? [];

        $cicd['platform'] = select(
            label: 'Select CI/CD platform',
            options: [
                'github_actions' => 'GitHub Actions (Recommended)',
                'gitlab_ci' => 'GitLab CI',
                'bitbucket' => 'Bitbucket Pipelines',
            ],
            default: $cicd['platform'] ?? 'github_actions',
        );

        $cicd['git_workflow'] = select(
            label: 'Select Git workflow',
            options: [
                'trunk_based' => 'Trunk-Based Development (Recommended)',
                'gitflow' => 'GitFlow',
                'github_flow' => 'GitHub Flow',
            ],
            default: $cicd['git_workflow'] ?? 'trunk_based',
        );

        $cicd['conventional_commits'] = confirm(
            label: 'Enforce conventional commits?',
            default: $cicd['conventional_commits'] ?? true,
        );

        $cicd['auto_merge_dependabot'] = confirm(
            label: 'Auto-merge Dependabot PRs for minor/patch updates?',
            default: $cicd['auto_merge_dependabot'] ?? false,
        );

        $config['ci_cd'] = $cicd;

        $io->text('CI/CD configured');

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function reconfigureObservability(array $config, SymfonyStyle $io): array
    {
        $obs = $config['observability'] ?? [];

        $obs['error_tracking'] = select(
            label: 'Select error tracking provider',
            options: [
                'none' => 'None',
                'sentry' => 'Sentry (Recommended)',
                'bugsnag' => 'Bugsnag',
                'flare' => 'Flare (Spatie)',
            ],
            default: $obs['error_tracking'] ?? 'sentry',
        );

        $obs['apm'] = select(
            label: 'Select APM provider',
            options: [
                'none' => 'None',
                'pulse' => 'Laravel Pulse (Free)',
                'telescope' => 'Laravel Telescope (Local)',
                'newrelic' => 'New Relic',
                'datadog' => 'Datadog',
            ],
            default: $obs['apm'] ?? 'pulse',
        );

        $obs['log_aggregation'] = select(
            label: 'Select log aggregation',
            options: [
                'none' => 'None (Local logs)',
                'betterstack' => 'Better Stack (Logtail)',
                'papertrail' => 'Papertrail',
                'cloudwatch' => 'AWS CloudWatch',
            ],
            default: $obs['log_aggregation'] ?? 'none',
        );

        $config['observability'] = $obs;

        $io->text('Observability configured');

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function reconfigureFrontend(array $config, SymfonyStyle $io): array
    {
        $frontend = $config['frontend'] ?? [];

        $frontend['framework'] = select(
            label: 'Select frontend framework',
            options: [
                'blade' => 'Blade (Server-side)',
                'livewire' => 'Livewire',
                'inertia_vue' => 'Inertia + Vue',
                'inertia_react' => 'Inertia + React',
                'vue_spa' => 'Vue SPA (API backend)',
                'react_spa' => 'React SPA (API backend)',
                'none' => 'API only (No frontend)',
            ],
            default: $frontend['framework'] ?? 'blade',
        );

        $frontend['css'] = select(
            label: 'Select CSS framework',
            options: [
                'tailwind' => 'Tailwind CSS (Recommended)',
                'bootstrap' => 'Bootstrap',
                'none' => 'Custom CSS',
            ],
            default: $frontend['css'] ?? 'tailwind',
        );

        $frontend['typescript'] = confirm(
            label: 'Use TypeScript?',
            default: $frontend['typescript'] ?? true,
        );

        $frontend['component_library'] = select(
            label: 'Select component library',
            options: [
                'none' => 'None (Custom)',
                'shadcn' => 'shadcn/ui (Recommended)',
                'headlessui' => 'Headless UI',
                'daisyui' => 'DaisyUI',
                'preline' => 'Preline',
            ],
            default: $frontend['component_library'] ?? 'shadcn',
        );

        $config['frontend'] = $frontend;

        $io->text('Frontend configured');

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function reconfigureSecurity(array $config, SymfonyStyle $io): array
    {
        $security = $config['security'] ?? [];

        $security['dependency_scanning'] = confirm(
            label: 'Enable dependency vulnerability scanning?',
            default: $security['dependency_scanning'] ?? true,
        );

        $security['secret_scanning'] = confirm(
            label: 'Enable secret/credential scanning?',
            default: $security['secret_scanning'] ?? true,
        );

        $security['owasp_hooks'] = confirm(
            label: 'Enable OWASP security hooks during development?',
            default: $security['owasp_hooks'] ?? true,
        );

        $config['security'] = $security;

        $io->text('Security configured');

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCurrentConfig(): array
    {
        $configPath = $this->laraforge->getWorkingDirectory().'/.laraforge/config.yaml';

        if (file_exists($configPath)) {
            $content = file_get_contents($configPath);
            if ($content !== false) {
                /** @var array<string, mixed>|null $parsed */
                $parsed = Yaml::parse($content);

                return $parsed ?? [];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function saveConfig(array $config): void
    {
        $configDir = $this->laraforge->getWorkingDirectory().'/.laraforge';
        $configPath = $configDir.'/config.yaml';

        if (! is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $yaml = Yaml::dump($config, 4, 2);
        file_put_contents($configPath, $yaml);
    }

    private function showConfig(SymfonyStyle $io): int
    {
        $config = $this->loadCurrentConfig();

        if (empty($config)) {
            $io->warning('No configuration found. Run `laraforge init` first.');

            return Command::FAILURE;
        }

        $io->section('Current Configuration');
        $io->writeln(Yaml::dump($config, 6, 2));

        return Command::SUCCESS;
    }

    private function resetConfig(SymfonyStyle $io): int
    {
        if (! confirm('Are you sure you want to reset to default configuration?', false)) {
            $io->note('Reset cancelled.');

            return Command::SUCCESS;
        }

        $configPath = $this->laraforge->getWorkingDirectory().'/.laraforge/config.yaml';

        if (file_exists($configPath)) {
            unlink($configPath);
        }

        $io->success('Configuration reset. Run `laraforge init` to reconfigure.');

        return Command::SUCCESS;
    }

    private function exportConfig(SymfonyStyle $io, string $path): int
    {
        $config = $this->loadCurrentConfig();

        if (empty($config)) {
            $io->error('No configuration found to export.');

            return Command::FAILURE;
        }

        $yaml = Yaml::dump($config, 6, 2);
        file_put_contents($path, $yaml);

        $io->success("Configuration exported to: {$path}");

        return Command::SUCCESS;
    }

    private function importConfig(SymfonyStyle $io, string $path): int
    {
        if (! file_exists($path)) {
            $io->error("File not found: {$path}");

            return Command::FAILURE;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $io->error("Could not read file: {$path}");

            return Command::FAILURE;
        }

        /** @var array<string, mixed>|null $config */
        $config = Yaml::parse($content);

        if ($config === null) {
            $io->error('Invalid YAML configuration.');

            return Command::FAILURE;
        }

        $this->saveConfig($config);

        $io->success('Configuration imported successfully!');

        return Command::SUCCESS;
    }
}
