<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\AgentSupport\AgentSupportFactory;
use LaraForge\Commands\Concerns\SuggestsNextStep;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'init',
    description: 'Initialize LaraForge in your project',
)]
final class InitCommand extends Command
{
    use SuggestsNextStep;

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Use default values without prompting')
            ->addOption('agents', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of agents to install (claude-code,cursor,jetbrains,windsurf)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $laraforgeDir = $workingDir.'/.laraforge';
        $filesystem = new Filesystem;

        // Check if already initialized
        if ($filesystem->exists($laraforgeDir) && ! $input->getOption('force')) {
            warning('LaraForge is already initialized in this project.');

            if (! confirm('Do you want to reinitialize?', false)) {
                return self::SUCCESS;
            }
        }

        info('🚀 Initializing LaraForge');

        // Gather project information
        $projectName = text(
            label: 'Project name',
            placeholder: 'my-project',
            default: basename($workingDir),
            required: true,
        );

        $projectDescription = text(
            label: 'Project description',
            placeholder: 'A brief description of your project',
            default: '',
        );

        // Detect framework
        $detectedFramework = $this->detectFramework($workingDir);
        $framework = select(
            label: 'Framework',
            options: [
                'laravel' => 'Laravel',
                'symfony' => 'Symfony',
                'slim' => 'Slim',
                'vanilla' => 'Vanilla PHP',
            ],
            default: $detectedFramework,
        );

        // Get agent registry
        $registry = AgentSupportFactory::create();

        // Select AI agents to configure
        $agentsOption = $input->getOption('agents');
        if ($agentsOption !== null && $agentsOption !== false) {
            // Use command-line option
            $selectedAgents = array_filter(array_map('trim', explode(',', $agentsOption)));
        } else {
            // Interactive selection using registry
            $agentOptions = $registry->getPromptOptions();
            $selectedAgents = multiselect(
                label: 'Which AI coding assistants do you use?',
                options: $agentOptions,
                default: [AgentSupportFactory::primaryAgent()],
                required: true,
                hint: 'Select all that apply. You can add more later with `laraforge agent:add`',
            );
        }

        // Select features
        $features = multiselect(
            label: 'Which features do you want to enable?',
            options: [
                'skills' => 'Skills (knowledge base for AI)',
                'commands' => 'Slash commands',
                'sub_agents' => 'Sub-agents',
                'quality' => 'Quality tools (PHPStan, Pint, Pest)',
                'ci' => 'CI/CD templates (GitHub Actions)',
                'docs' => 'Documentation templates (PRD, FRD, Tech Spec)',
            ],
            default: ['skills', 'commands', 'quality', 'docs'],
        );

        // Create directory structure
        spin(
            message: 'Creating LaraForge configuration...',
            callback: function () use ($filesystem, $laraforgeDir, $projectName, $projectDescription, $framework, $selectedAgents, $features): void {
                // Create .laraforge directory structure
                $filesystem->mkdir($laraforgeDir);
                $filesystem->mkdir($laraforgeDir.'/templates');
                $filesystem->mkdir($laraforgeDir.'/stubs');
                $filesystem->mkdir($laraforgeDir.'/plugins');
                $filesystem->mkdir($laraforgeDir.'/docs');
                $filesystem->mkdir($laraforgeDir.'/criteria');
                $filesystem->mkdir($laraforgeDir.'/agents');

                // Create config.yaml
                $config = $this->generateConfig($projectName, $projectDescription, $framework, $selectedAgents, $features);
                $filesystem->dumpFile($laraforgeDir.'/config.yaml', $config);

                // Create .gitkeep files
                $filesystem->touch($laraforgeDir.'/templates/.gitkeep');
                $filesystem->touch($laraforgeDir.'/stubs/.gitkeep');
                $filesystem->touch($laraforgeDir.'/plugins/.gitkeep');
                $filesystem->touch($laraforgeDir.'/docs/.gitkeep');
                $filesystem->touch($laraforgeDir.'/criteria/.gitkeep');
            },
        );

        // Install selected AI agents using the new agent support system
        $installedAgents = [];
        foreach ($selectedAgents as $agentId) {
            $support = $registry->get($agentId);
            if ($support !== null) {
                $result = spin(
                    message: "Installing {$support->name()} support...",
                    callback: fn () => $support->install($workingDir),
                );

                if ($result['success']) {
                    $installedAgents[] = $support->name();
                    foreach ($result['messages'] ?? [] as $message) {
                        $output->writeln("  - {$message}");
                    }
                }
            }
        }

        // Generate VS Code settings if requested (not covered by agent support)
        if (in_array('quality', $features, true)) {
            spin(
                message: 'Configuring VS Code settings...',
                callback: fn () => $this->generateVsCodeSettings($filesystem, $workingDir),
            );
        }

        outro('✅ LaraForge initialized successfully!');

        // Show summary
        if (! empty($installedAgents)) {
            note('Installed AI agent support: '.implode(', ', $installedAgents));
        }

        note('Configuration stored in: .laraforge/config.yaml');
        note('Use `laraforge agent:list` to see all available agents');
        note('Use `laraforge agent:sync` to update agent configs when docs change');

        // Mark 'init' step complete and suggest what's next
        $this->completeStepAndSuggestNext('init', $output);

        return self::SUCCESS;
    }

    private function detectFramework(string $path): string
    {
        // Check for Laravel
        if (file_exists($path.'/artisan') && file_exists($path.'/bootstrap/app.php')) {
            return 'laravel';
        }

        // Check for Symfony
        if (file_exists($path.'/bin/console') && file_exists($path.'/config/bundles.php')) {
            return 'symfony';
        }

        // Check for Slim
        if (file_exists($path.'/composer.json')) {
            $content = file_get_contents($path.'/composer.json');
            if ($content !== false) {
                $composer = json_decode($content, true);
                if (isset($composer['require']['slim/slim'])) {
                    return 'slim';
                }
            }
        }

        return 'vanilla';
    }

    /**
     * @param  array<string>  $agents
     * @param  array<string>  $features
     */
    private function generateConfig(
        string $projectName,
        string $projectDescription,
        string $framework,
        array $agents,
        array $features,
    ): string {
        $config = [
            'project' => [
                'name' => $projectName,
                'description' => $projectDescription,
            ],
            'framework' => $framework,
            'agents' => $agents,
            'features' => array_fill_keys($features, true),
        ];

        return "# LaraForge Configuration\n# Documentation: https://github.com/laraforge/laraforge\n\n".
            \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
    }

    private function generateVsCodeSettings(Filesystem $filesystem, string $workingDir): void
    {
        $vscodeDir = $workingDir.'/.vscode';
        $filesystem->mkdir($vscodeDir);

        // Generate recommended settings for PHP/Laravel development
        $settings = [
            'php.validate.executablePath' => '/usr/local/bin/php',
            'editor.formatOnSave' => true,
            'editor.defaultFormatter' => 'bmewburn.vscode-intelephense-client',
            '[php]' => [
                'editor.defaultFormatter' => 'open-southeners.laravel-pint',
            ],
            'phpstan.enabled' => true,
            'phpstan.configFile' => 'phpstan.neon.dist',
            'pest.path' => './vendor/bin/pest',
        ];

        $filesystem->dumpFile(
            $vscodeDir.'/settings.json',
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Generate recommended extensions
        $extensions = [
            'recommendations' => [
                'bmewburn.vscode-intelephense-client',
                'open-southeners.laravel-pint',
                'swordev.phpstan',
                'open-southeners.vscode-pest',
                'bradlc.vscode-tailwindcss',
                'vue.volar',
            ],
        ];

        $filesystem->dumpFile(
            $vscodeDir.'/extensions.json',
            json_encode($extensions, JSON_PRETTY_PRINT)
        );
    }
}
