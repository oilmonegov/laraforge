<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'generators',
    description: 'List available generators',
    aliases: ['list:generators'],
)]
final class ListGeneratorsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $generators = $this->laraforge->generators();

        if (empty($generators)) {
            warning('No generators available.');
            info('Install a framework adapter (e.g., oilmonegov/laraforge-laravel) to get started.');
            return self::SUCCESS;
        }

        info('Available Generators');

        $rows = [];
        foreach ($generators as $name => $generator) {
            $rows[] = [
                $name,
                $generator->name(),
                $generator->description(),
            ];
        }

        table(
            headers: ['Identifier', 'Name', 'Description'],
            rows: $rows,
        );

        $output->writeln('');
        $output->writeln('Usage: <info>laraforge generate [generator]</info>');

        return self::SUCCESS;
    }
}
