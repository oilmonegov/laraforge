<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\AgentSupport\AgentSupportFactory;
use LaraForge\Commands\Concerns\SuggestsNextStep;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'agent:add',
    description: 'Add AI agent support to your project',
)]
final class AgentAddCommand extends Command
{
    use SuggestsNextStep;

    protected function configure(): void
    {
        $this
            ->addArgument('agent', InputArgument::OPTIONAL, 'Agent identifier (claude-code, cursor, jetbrains, windsurf)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Install all available agents')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $registry = AgentSupportFactory::create();

        // Check if .laraforge directory exists
        if (! is_dir($workingDir.'/.laraforge')) {
            error('LaraForge is not initialized in this project.');
            info('Run `laraforge init` first to initialize LaraForge.');

            return self::FAILURE;
        }

        $agentId = $input->getArgument('agent');
        $installAll = $input->getOption('all');
        $force = $input->getOption('force');

        // Determine which agents to install
        $agentsToInstall = [];

        if ($installAll) {
            // Install all available agents
            $agentsToInstall = array_keys($registry->available($workingDir));
            if (empty($agentsToInstall)) {
                warning('All agents are already installed.');

                return self::SUCCESS;
            }
        } elseif ($agentId !== null) {
            // Install specific agent
            $support = $registry->get($agentId);
            if ($support === null) {
                error("Unknown agent: {$agentId}");
                info('Available agents: '.implode(', ', AgentSupportFactory::availableAgents()));

                return self::FAILURE;
            }

            if ($support->isInstalled($workingDir) && ! $force) {
                warning("{$support->name()} is already installed.");

                if (! confirm('Do you want to reinstall?', false)) {
                    return self::SUCCESS;
                }
            }

            $agentsToInstall = [$agentId];
        } else {
            // Interactive selection
            $available = $registry->available($workingDir);
            $installed = $registry->installed($workingDir);

            if (empty($available) && empty($installed)) {
                warning('No agents are available to install.');

                return self::SUCCESS;
            }

            // Build options with status indicators
            $options = [];
            foreach ($registry->allByPriority() as $support) {
                $status = $support->isInstalled($workingDir) ? ' (installed)' : '';
                $options[$support->identifier()] = $support->name().$status;
            }

            $selected = multiselect(
                label: 'Which AI agents do you want to add?',
                options: $options,
                required: true,
                hint: 'Already installed agents will be reinstalled',
            );

            $agentsToInstall = $selected;
        }

        if (empty($agentsToInstall)) {
            warning('No agents selected.');

            return self::SUCCESS;
        }

        info('🤖 Installing AI Agent Support');

        $results = [];
        foreach ($agentsToInstall as $identifier) {
            $support = $registry->get($identifier);
            if ($support === null) {
                continue;
            }

            $results[$identifier] = spin(
                message: "Installing {$support->name()}...",
                callback: fn () => $support->install($workingDir),
            );
        }

        // Show results
        $output->writeln('');
        $successCount = 0;
        foreach ($results as $identifier => $result) {
            $support = $registry->get($identifier);
            if ($support === null) {
                continue;
            }

            if ($result['success']) {
                $successCount++;
                info("✅ {$support->name()} installed successfully");

                foreach ($result['messages'] ?? [] as $message) {
                    $output->writeln("   - {$message}");
                }

                if (! empty($result['files_created'])) {
                    $output->writeln('   Files created:');
                    foreach ($result['files_created'] as $file) {
                        $relativePath = str_replace($workingDir.'/', '', $file);
                        $output->writeln("     - {$relativePath}");
                    }
                }
            } else {
                error("❌ Failed to install {$support->name()}: ".($result['error'] ?? 'Unknown error'));
            }
        }

        $output->writeln('');
        outro("✅ Installed {$successCount} agent(s) successfully!");

        // Suggest next step
        $this->completeStepAndSuggestNext('agent:add', $output);

        return self::SUCCESS;
    }
}
