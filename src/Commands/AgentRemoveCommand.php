<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\AgentSupport\AgentSupportFactory;
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
    name: 'agent:remove',
    description: 'Remove AI agent support from your project',
)]
final class AgentRemoveCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('agent', InputArgument::OPTIONAL, 'Agent identifier (claude-code, cursor, jetbrains, windsurf)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Remove all installed agents')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $registry = AgentSupportFactory::create();

        $agentId = $input->getArgument('agent');
        $removeAll = $input->getOption('all');
        $force = $input->getOption('force');

        // Determine which agents to remove
        $agentsToRemove = [];

        if ($removeAll) {
            // Remove all installed agents
            $agentsToRemove = array_keys($registry->installed($workingDir));
            if (empty($agentsToRemove)) {
                warning('No agents are currently installed.');

                return self::SUCCESS;
            }

            if (! $force && ! confirm('Are you sure you want to remove ALL installed agents?', false)) {
                return self::SUCCESS;
            }
        } elseif ($agentId !== null) {
            // Remove specific agent
            $support = $registry->get($agentId);
            if ($support === null) {
                error("Unknown agent: {$agentId}");
                info('Available agents: '.implode(', ', AgentSupportFactory::availableAgents()));

                return self::FAILURE;
            }

            if (! $support->isInstalled($workingDir)) {
                warning("{$support->name()} is not installed.");

                return self::SUCCESS;
            }

            if (! $force && ! confirm("Are you sure you want to remove {$support->name()}?", false)) {
                return self::SUCCESS;
            }

            $agentsToRemove = [$agentId];
        } else {
            // Interactive selection
            $installed = $registry->installed($workingDir);

            if (empty($installed)) {
                warning('No agents are currently installed.');

                return self::SUCCESS;
            }

            // Build options
            $options = [];
            foreach ($installed as $identifier => $support) {
                $options[$identifier] = $support->name();
            }

            $selected = multiselect(
                label: 'Which AI agents do you want to remove?',
                options: $options,
                required: true,
            );

            if (empty($selected)) {
                warning('No agents selected.');

                return self::SUCCESS;
            }

            if (! $force && ! confirm('Are you sure you want to remove the selected agents?', false)) {
                return self::SUCCESS;
            }

            $agentsToRemove = $selected;
        }

        if (empty($agentsToRemove)) {
            warning('No agents to remove.');

            return self::SUCCESS;
        }

        info('🗑️  Removing AI Agent Support');

        $results = [];
        foreach ($agentsToRemove as $identifier) {
            $support = $registry->get($identifier);
            if ($support === null) {
                continue;
            }

            $results[$identifier] = spin(
                message: "Removing {$support->name()}...",
                callback: fn () => $support->uninstall($workingDir),
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
                info("✅ {$support->name()} removed successfully");

                foreach ($result['messages'] ?? [] as $message) {
                    $output->writeln("   - {$message}");
                }

                if (! empty($result['files_removed'])) {
                    $output->writeln('   Files removed:');
                    foreach ($result['files_removed'] as $file) {
                        $relativePath = str_replace($workingDir.'/', '', $file);
                        $output->writeln("     - {$relativePath}");
                    }
                }
            } else {
                error("❌ Failed to remove {$support->name()}: ".($result['error'] ?? 'Unknown error'));
            }
        }

        $output->writeln('');
        outro("✅ Removed {$successCount} agent(s) successfully!");

        return self::SUCCESS;
    }
}
