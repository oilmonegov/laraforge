<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'criteria:init',
    description: 'Create an acceptance criteria YAML template',
)]
final class CriteriaInitCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('feature', InputArgument::OPTIONAL, 'The feature name for the criteria file')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $feature = $input->getArgument('feature');
        $force = $input->getOption('force');

        // Prompt for feature name if not provided
        if ($feature === null) {
            $feature = text(
                label: 'What is the feature name?',
                placeholder: 'e.g., user-registration',
                required: true,
            );
        }

        // Normalize feature name to kebab-case
        $featureName = $this->normalizeFeatureName($feature);

        // Get criteria directory
        $criteriaDirectory = $this->laraforge->criteriaLoader()->defaultDirectory();

        // Create directory if it doesn't exist
        $filesystem = new Filesystem;
        if (! $filesystem->exists($criteriaDirectory)) {
            $filesystem->mkdir($criteriaDirectory);
            info("Created criteria directory: {$criteriaDirectory}");
        }

        // Build file path
        $filePath = "{$criteriaDirectory}/{$featureName}.yaml";

        // Check if file exists
        if ($filesystem->exists($filePath) && ! $force) {
            warning("File already exists: {$filePath}");
            error('Use --force to overwrite');

            return self::FAILURE;
        }

        // Generate criteria content
        $content = $this->generateCriteriaContent($featureName);

        // Write file
        $filesystem->dumpFile($filePath, $content);

        outro("Created criteria file: {$filePath}");

        info('Edit this file to add your acceptance criteria.');
        $output->writeln('');
        $output->writeln('Example criteria format:');
        $output->writeln('  - id: "AC-001"');
        $output->writeln('    description: "User can perform action"');
        $output->writeln('    assertions:');
        $output->writeln('      - "expected result occurs"');

        return self::SUCCESS;
    }

    private function normalizeFeatureName(string $feature): string
    {
        // Convert to lowercase and replace spaces/underscores with hyphens
        $normalized = strtolower($feature);
        $normalized = (string) preg_replace('/[\s_]+/', '-', $normalized);
        $normalized = (string) preg_replace('/[^a-z0-9-]/', '', $normalized);
        $normalized = trim($normalized, '-');

        return $normalized;
    }

    private function generateCriteriaContent(string $featureName): string
    {
        $featureTitle = $this->featureToTitle($featureName);

        $data = [
            'feature' => $featureTitle,
            'criteria' => [
                [
                    'id' => 'AC-001',
                    'description' => 'First acceptance criterion',
                    'assertions' => [
                        'expected behavior occurs',
                        'system state is valid',
                    ],
                ],
                [
                    'id' => 'AC-002',
                    'description' => 'Second acceptance criterion',
                    'assertions' => [
                        'another expected behavior',
                    ],
                ],
            ],
        ];

        return Yaml::dump($data, 4, 2);
    }

    private function featureToTitle(string $featureName): string
    {
        return ucwords(str_replace('-', ' ', $featureName));
    }
}
