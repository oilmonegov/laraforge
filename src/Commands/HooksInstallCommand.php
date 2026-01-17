<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Generators\GitHooksGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'hooks:install',
    description: 'Install git hooks for code quality enforcement',
    aliases: ['hooks'],
)]
final class HooksInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('hooks', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of hooks to install')
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Directory to store hooks', '.githooks')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing hooks')
            ->addOption('no-configure', null, InputOption::VALUE_NONE, 'Do not configure git to use hooks directory')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Use default values without prompting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $directory = $input->getOption('directory');
        $force = $input->getOption('force');
        $noInteraction = $input->getOption('no-interaction');

        // Check if git repository
        if (! is_dir($workingDir.'/.git')) {
            warning('This directory is not a git repository.');

            if (! $noInteraction && confirm('Initialize a git repository?', true)) {
                exec("cd {$workingDir} && git init");
                info('Git repository initialized.');
            } else {
                return self::FAILURE;
            }
        }

        // Check for existing hooks
        $hooksDir = $workingDir.'/'.$directory;
        if (is_dir($hooksDir) && ! $force) {
            warning("Hooks directory '{$directory}' already exists.");

            if (! $noInteraction && ! confirm('Do you want to overwrite existing hooks?', false)) {
                return self::SUCCESS;
            }
        }

        // Get hooks to install
        $hooks = $this->getHooksToInstall($input, $noInteraction);

        if (empty($hooks)) {
            warning('No hooks selected.');

            return self::SUCCESS;
        }

        info('Installing git hooks...');

        // Generate hooks
        $generator = new GitHooksGenerator($this->laraforge);

        $generatedFiles = spin(
            message: 'Generating hook files...',
            callback: fn () => $generator->generate([
                'hooks' => $hooks,
                'directory' => $directory,
                'configure_git' => ! $input->getOption('no-configure'),
            ]),
        );

        outro('Git hooks installed successfully!');

        // Show what was installed
        $output->writeln('');
        $output->writeln('<info>Installed hooks:</info>');
        foreach ($generatedFiles as $file) {
            $relativePath = str_replace($workingDir.'/', '', $file);
            $output->writeln("  - {$relativePath}");
        }

        $output->writeln('');
        $output->writeln('<comment>Hooks are now active. To disable temporarily, use:</comment>');
        $output->writeln('  git commit --no-verify');
        $output->writeln('');
        $output->writeln('<comment>To customize hooks, edit the files in:</comment>');
        $output->writeln("  {$directory}/");

        return self::SUCCESS;
    }

    /**
     * @return array<string>
     */
    private function getHooksToInstall(InputInterface $input, bool $noInteraction): array
    {
        $hooksOption = $input->getOption('hooks');

        if ($hooksOption !== null) {
            return array_map('trim', explode(',', $hooksOption));
        }

        if ($noInteraction) {
            return ['pre-commit', 'commit-msg'];
        }

        $options = [];
        foreach (GitHooksGenerator::AVAILABLE_HOOKS as $hook => $description) {
            $options[$hook] = "{$hook} - {$description}";
        }

        return multiselect(
            label: 'Which hooks do you want to install?',
            options: $options,
            default: ['pre-commit', 'commit-msg'],
            required: true,
        );
    }
}
