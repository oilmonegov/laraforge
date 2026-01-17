<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

#[AsCommand(
    name: 'version',
    description: 'Display LaraForge version information',
    aliases: ['--version', '-v'],
)]
final class VersionCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        info("LaraForge v{$this->laraforge->version()}");

        // Show active adapter
        $adapter = $this->laraforge->adapter();
        if ($adapter !== null) {
            $output->writeln("Framework: <info>{$adapter->name()}</info> (v{$adapter->version()})");
        }

        // Show plugins
        $plugins = $this->laraforge->plugins();
        if (!empty($plugins)) {
            $output->writeln('');
            $output->writeln('Plugins:');

            $rows = [];
            foreach ($plugins as $plugin) {
                $rows[] = [$plugin->name(), $plugin->version()];
            }

            table(
                headers: ['Name', 'Version'],
                rows: $rows,
            );
        }

        // Show adapters
        $adapters = $this->laraforge->adapters();
        if (!empty($adapters)) {
            $output->writeln('');
            $output->writeln('Adapters:');

            $rows = [];
            foreach ($adapters as $adapterItem) {
                $active = $adapter?->identifier() === $adapterItem->identifier() ? '✓' : '';
                $rows[] = [$adapterItem->name(), $adapterItem->version(), $active];
            }

            table(
                headers: ['Name', 'Version', 'Active'],
                rows: $rows,
            );
        }

        return self::SUCCESS;
    }
}
