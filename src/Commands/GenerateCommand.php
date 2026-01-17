<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

#[AsCommand(
    name: 'generate',
    description: 'Generate files using a generator',
)]
final class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('generator', InputArgument::OPTIONAL, 'The generator to use')
            ->addOption('option', 'o', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Generator options (key=value)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $generatorName = $input->getArgument('generator');
        $generators = $this->laraforge->generators();

        if (empty($generators)) {
            error('No generators available. Install a framework adapter or plugin.');

            return self::FAILURE;
        }

        // If no generator specified, prompt for selection
        if ($generatorName === null) {
            $options = [];
            foreach ($generators as $name => $generator) {
                $options[$name] = "{$generator->name()} - {$generator->description()}";
            }

            $generatorName = select(
                label: 'Which generator do you want to use?',
                options: $options,
            );
        }

        // Find the generator
        $generator = $this->laraforge->generator($generatorName);

        if ($generator === null) {
            error("Generator '{$generatorName}' not found.");
            info('Available generators: '.implode(', ', array_keys($generators)));

            return self::FAILURE;
        }

        // Parse options
        $options = $this->parseOptions($input->getOption('option'));

        // Prompt for required options that weren't provided
        foreach ($generator->options() as $optionName => $optionConfig) {
            if ($optionConfig['required'] && ! isset($options[$optionName])) {
                $options[$optionName] = \Laravel\Prompts\text(
                    label: $optionConfig['description'],
                    required: true,
                );
            }
        }

        // Validate options
        try {
            $generator->validate($options);
        } catch (\LaraForge\Exceptions\ValidationException $e) {
            error('Validation failed:');
            foreach ($e->errors() as $field => $errors) {
                foreach ($errors as $errorMessage) {
                    $output->writeln("  - {$field}: {$errorMessage}");
                }
            }

            return self::FAILURE;
        }

        // Generate files
        $generatedFiles = spin(
            message: "Generating with {$generator->name()}...",
            callback: fn () => $generator->generate($options),
        );

        outro('✅ Generation complete!');

        info('Generated files:');
        foreach ($generatedFiles as $file) {
            $output->writeln("  - {$file}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string>  $options
     * @return array<string, string>
     */
    private function parseOptions(array $options): array
    {
        $parsed = [];

        foreach ($options as $option) {
            if (str_contains($option, '=')) {
                [$key, $value] = explode('=', $option, 2);
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }
}
