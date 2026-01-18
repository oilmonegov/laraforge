<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\AgentSupport\AgentSupportFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;

#[AsCommand(
    name: 'agent:list',
    description: 'List all available AI agent supports',
)]
final class AgentListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $registry = AgentSupportFactory::create();

        info('📋 Available AI Agent Supports');

        $tableData = [];
        foreach ($registry->allByPriority() as $support) {
            $isInstalled = $support->isInstalled($workingDir);
            $status = $isInstalled ? '✅ Installed' : '○ Available';

            $tableData[] = [
                $support->identifier(),
                $support->name(),
                $status,
                $support->priority(),
            ];
        }

        table(
            headers: ['ID', 'Name', 'Status', 'Priority'],
            rows: $tableData,
        );

        note('Use `laraforge agent:add <agent-id>` to install an agent support.');
        note('Use `laraforge agent:remove <agent-id>` to remove an agent support.');

        // Show installed agents info
        $installed = $registry->installed($workingDir);
        if (count($installed) > 0) {
            $output->writeln('');
            info('Installed agents create these root files:');
            foreach ($installed as $support) {
                foreach ($support->getRootFiles() as $file) {
                    $output->writeln("  - {$file}");
                }
            }
        }

        return self::SUCCESS;
    }
}
