<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
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
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Use default values without prompting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $laraforgeDir = $workingDir . '/.laraforge';
        $filesystem = new Filesystem();

        // Check if already initialized
        if ($filesystem->exists($laraforgeDir) && !$input->getOption('force')) {
            warning('LaraForge is already initialized in this project.');

            if (!confirm('Do you want to reinitialize?', false)) {
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

        // Select AI tools to configure
        $aiTools = multiselect(
            label: 'Which AI tools do you use?',
            options: [
                'claude' => 'Claude Code',
                'cursor' => 'Cursor',
                'vscode' => 'VS Code (with Copilot)',
            ],
            default: ['claude'],
            required: true,
        );

        // Select features
        $features = multiselect(
            label: 'Which features do you want to enable?',
            options: [
                'skills' => 'Skills (knowledge base for AI)',
                'commands' => 'Slash commands',
                'agents' => 'Sub-agents',
                'quality' => 'Quality tools (PHPStan, Pint, Pest)',
                'ci' => 'CI/CD templates (GitHub Actions)',
            ],
            default: ['skills', 'commands', 'quality'],
        );

        // Create directory structure
        spin(
            message: 'Creating LaraForge configuration...',
            callback: function () use ($filesystem, $laraforgeDir, $projectName, $projectDescription, $framework, $aiTools, $features): void {
                // Create .laraforge directory
                $filesystem->mkdir($laraforgeDir);
                $filesystem->mkdir($laraforgeDir . '/templates');
                $filesystem->mkdir($laraforgeDir . '/stubs');
                $filesystem->mkdir($laraforgeDir . '/plugins');

                // Create config.yaml
                $config = $this->generateConfig($projectName, $projectDescription, $framework, $aiTools, $features);
                $filesystem->dumpFile($laraforgeDir . '/config.yaml', $config);

                // Create .gitkeep files
                $filesystem->touch($laraforgeDir . '/templates/.gitkeep');
                $filesystem->touch($laraforgeDir . '/stubs/.gitkeep');
                $filesystem->touch($laraforgeDir . '/plugins/.gitkeep');
            },
        );

        // Generate initial files based on selections
        spin(
            message: 'Generating AI configuration files...',
            callback: function () use ($filesystem, $workingDir, $aiTools, $features, $projectName, $projectDescription): void {
                // Generate CLAUDE.md if Claude is selected
                if (in_array('claude', $aiTools, true)) {
                    $this->generateClaudeMd($filesystem, $workingDir, $projectName, $projectDescription, $features);
                }

                // Generate .cursorrules if Cursor is selected
                if (in_array('cursor', $aiTools, true)) {
                    $this->generateCursorRules($filesystem, $workingDir, $projectName);
                }

                // Generate VS Code settings if VS Code is selected
                if (in_array('vscode', $aiTools, true)) {
                    $this->generateVsCodeSettings($filesystem, $workingDir);
                }
            },
        );

        outro('✅ LaraForge initialized successfully!');

        info('Next steps:');
        $output->writeln('  1. Review and customize .laraforge/config.yaml');
        $output->writeln('  2. Run <info>laraforge generate:claude</info> to regenerate CLAUDE.md');
        $output->writeln('  3. Add your custom templates to .laraforge/templates/');

        return self::SUCCESS;
    }

    private function detectFramework(string $path): string
    {
        // Check for Laravel
        if (file_exists($path . '/artisan') && file_exists($path . '/bootstrap/app.php')) {
            return 'laravel';
        }

        // Check for Symfony
        if (file_exists($path . '/bin/console') && file_exists($path . '/config/bundles.php')) {
            return 'symfony';
        }

        // Check for Slim
        if (file_exists($path . '/composer.json')) {
            $composer = json_decode(file_get_contents($path . '/composer.json'), true);
            if (isset($composer['require']['slim/slim'])) {
                return 'slim';
            }
        }

        return 'vanilla';
    }

    /**
     * @param array<string> $aiTools
     * @param array<string> $features
     */
    private function generateConfig(
        string $projectName,
        string $projectDescription,
        string $framework,
        array $aiTools,
        array $features,
    ): string {
        $config = [
            'project' => [
                'name' => $projectName,
                'description' => $projectDescription,
            ],
            'framework' => $framework,
            'ai_tools' => $aiTools,
            'features' => array_fill_keys($features, true),
        ];

        return "# LaraForge Configuration\n# Documentation: https://github.com/oilmonegov/laraforge\n\n" .
            \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
    }

    /**
     * @param array<string> $features
     */
    private function generateClaudeMd(
        Filesystem $filesystem,
        string $workingDir,
        string $projectName,
        string $projectDescription,
        array $features,
    ): void {
        $content = $this->laraforge->templates()->renderFile('CLAUDE.md.template', [
            'projectName' => $projectName,
            'projectDescription' => $projectDescription,
            'hasSkills' => in_array('skills', $features, true),
            'hasCommands' => in_array('commands', $features, true),
            'hasAgents' => in_array('agents', $features, true),
        ]);

        $filesystem->dumpFile($workingDir . '/CLAUDE.md', $content);
    }

    private function generateCursorRules(Filesystem $filesystem, string $workingDir, string $projectName): void
    {
        if ($this->laraforge->templates()->exists('cursor/rules.template')) {
            $content = $this->laraforge->templates()->renderFile('cursor/rules.template', [
                'projectName' => $projectName,
            ]);
            $filesystem->dumpFile($workingDir . '/.cursorrules', $content);
        }
    }

    private function generateVsCodeSettings(Filesystem $filesystem, string $workingDir): void
    {
        $vscodeDir = $workingDir . '/.vscode';
        $filesystem->mkdir($vscodeDir);

        if ($this->laraforge->templates()->exists('vscode/settings.json')) {
            $content = $this->laraforge->templates()->renderFile('vscode/settings.json', []);
            $filesystem->dumpFile($vscodeDir . '/settings.json', $content);
        }

        if ($this->laraforge->templates()->exists('vscode/extensions.json')) {
            $content = $this->laraforge->templates()->renderFile('vscode/extensions.json', []);
            $filesystem->dumpFile($vscodeDir . '/extensions.json', $content);
        }
    }
}
