<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\AgentSupport\AgentSupportFactory;
use LaraForge\Commands\Concerns\SuggestsNextStep;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'agent:sync',
    description: 'Sync AI agent configurations with project documentation',
)]
final class AgentSyncCommand extends Command
{
    use SuggestsNextStep;

    protected function configure(): void
    {
        $this
            ->addArgument('agent', InputArgument::OPTIONAL, 'Agent identifier to sync (syncs all if omitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $registry = AgentSupportFactory::create();

        $agentId = $input->getArgument('agent');

        // Determine which agents to sync
        $agentsToSync = [];

        if ($agentId !== null) {
            // Sync specific agent
            $support = $registry->get($agentId);
            if ($support === null) {
                error("Unknown agent: {$agentId}");
                info('Available agents: '.implode(', ', AgentSupportFactory::availableAgents()));

                return self::FAILURE;
            }

            if (! $support->isInstalled($workingDir)) {
                warning("{$support->name()} is not installed. Use `laraforge agent:add {$agentId}` to install.");

                return self::FAILURE;
            }

            $agentsToSync = [$agentId => $support];
        } else {
            // Sync all installed agents
            $agentsToSync = $registry->installed($workingDir);

            if (empty($agentsToSync)) {
                warning('No agents are currently installed.');
                info('Use `laraforge agent:add` to install an agent first.');

                return self::SUCCESS;
            }
        }

        info('🔄 Syncing AI Agent Configurations');

        $results = [];
        foreach ($agentsToSync as $identifier => $support) {
            $results[$identifier] = spin(
                message: "Syncing {$support->name()}...",
                callback: fn () => $support->sync($workingDir),
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
                info("✅ {$support->name()} synced successfully");

                foreach ($result['messages'] ?? [] as $message) {
                    $output->writeln("   - {$message}");
                }

                if (! empty($result['files_updated'])) {
                    $output->writeln('   Files updated:');
                    foreach ($result['files_updated'] as $file) {
                        $relativePath = str_replace($workingDir.'/', '', $file);
                        $output->writeln("     - {$relativePath}");
                    }
                }
            } else {
                error("❌ Failed to sync {$support->name()}: ".($result['error'] ?? 'Unknown error'));
            }
        }

        $output->writeln('');
        outro("✅ Synced {$successCount} agent(s) successfully!");

        // Suggest next step
        $this->completeStepAndSuggestNext('agent:sync', $output);

        return self::SUCCESS;
    }
}
