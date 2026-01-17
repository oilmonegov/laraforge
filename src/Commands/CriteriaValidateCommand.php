<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Exceptions\ConfigurationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'criteria:validate',
    description: 'Validate test coverage against acceptance criteria',
)]
final class CriteriaValidateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('feature', InputArgument::REQUIRED, 'The feature name or criteria file path')
            ->addOption('tests', 't', InputOption::VALUE_REQUIRED, 'Path to test file or directory', 'tests');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $feature = $input->getArgument('feature');
        $testPath = $input->getOption('tests');

        // Resolve criteria file path
        $criteriaPath = $this->resolveCriteriaPath($feature);

        if ($criteriaPath === null) {
            error("Criteria file not found for feature: {$feature}");
            info('Run `laraforge criteria:init '.$feature.'` to create one.');

            return self::FAILURE;
        }

        // Load criteria
        try {
            $criteria = $this->laraforge->criteriaLoader()->load($criteriaPath);
        } catch (ConfigurationException $e) {
            error('Failed to load criteria: '.$e->getMessage());

            return self::FAILURE;
        }

        info("Validating criteria for: {$criteria->feature}");
        $output->writeln("Criteria file: {$criteriaPath}");
        $output->writeln("Test path: {$testPath}");
        $output->writeln('');

        // Resolve test path
        $resolvedTestPath = $this->resolveTestPath($testPath);

        if ($resolvedTestPath === null) {
            error("Test path not found: {$testPath}");

            return self::FAILURE;
        }

        // Validate coverage
        $result = $this->laraforge->criteriaLoader()->validate($criteria, $resolvedTestPath);

        // Display results
        $this->displayResults($output, $result);

        if ($result->isFullyCovered()) {
            outro('All criteria are covered by tests!');

            return self::SUCCESS;
        }

        warning(sprintf(
            'Coverage: %.1f%% (%d/%d criteria covered)',
            $result->coveragePercentage(),
            count($result->coveredIds),
            $criteria->count(),
        ));

        return self::FAILURE;
    }

    private function resolveCriteriaPath(string $feature): ?string
    {
        // Check if it's already a file path
        if (file_exists($feature)) {
            return $feature;
        }

        // Look in criteria directory
        $criteriaLoader = $this->laraforge->criteriaLoader();

        return $criteriaLoader->find($feature, $criteriaLoader->defaultDirectory());
    }

    private function resolveTestPath(string $testPath): ?string
    {
        $workingDir = $this->laraforge->workingDirectory();

        // Try absolute path first
        if (file_exists($testPath)) {
            return $testPath;
        }

        // Try relative to working directory
        $relativePath = rtrim($workingDir, '/').'/'.$testPath;
        if (file_exists($relativePath)) {
            return $relativePath;
        }

        return null;
    }

    private function displayResults(OutputInterface $output, \LaraForge\Criteria\ValidationResult $result): void
    {
        if (! empty($result->coveredIds)) {
            $output->writeln('<info>Covered criteria:</info>');
            foreach ($result->coveredCriteria() as $criterion) {
                $output->writeln("  [x] {$criterion->id}: {$criterion->description}");
            }
            $output->writeln('');
        }

        if (! empty($result->missingIds)) {
            $output->writeln('<comment>Missing criteria:</comment>');
            foreach ($result->missingCriteria() as $criterion) {
                $output->writeln("  [ ] {$criterion->id}: {$criterion->description}");
            }
            $output->writeln('');
        }
    }
}
