<?php

declare(strict_types=1);

namespace LaraForge\Support;

use LaraForge\Criteria\AcceptanceCriteria;
use LaraForge\Enums\ImplementationStyle;

/**
 * Trait for generators that support TDD mode.
 *
 * Provides common functionality for generating tests before implementation.
 */
trait TddAwareGenerator
{
    /**
     * Get the implementation style from options.
     *
     * @param  array<string, mixed>  $options
     */
    protected function getImplementationStyle(array $options): ImplementationStyle
    {
        if (isset($options['style'])) {
            return ImplementationStyle::fromString((string) $options['style']);
        }

        $configStyle = $this->laraforge->config()->get('implementation.style', 'regular');

        return ImplementationStyle::fromString((string) $configStyle);
    }

    /**
     * Check if TDD mode is enabled for the given options.
     *
     * @param  array<string, mixed>  $options
     */
    protected function isTddMode(array $options): bool
    {
        return $this->getImplementationStyle($options) === ImplementationStyle::TDD;
    }

    /**
     * Get the test framework to use.
     */
    protected function getTestFramework(): string
    {
        return (string) $this->laraforge->config()->get('implementation.tdd.test_framework', 'pest');
    }

    /**
     * Check if criteria are required for TDD mode.
     */
    protected function requiresCriteria(): bool
    {
        return (bool) $this->laraforge->config()->get('implementation.tdd.require_criteria', false);
    }

    /**
     * Generate test file content from acceptance criteria.
     */
    protected function generateTestFromCriteria(AcceptanceCriteria $criteria, string $className): string
    {
        return $this->renderStub('pest-test', [
            'feature' => $criteria->feature,
            'className' => $className,
            'criteria' => $criteria->all(),
        ]);
    }

    /**
     * Get the test file path for a given class.
     */
    protected function getTestFilePath(string $className, string $testDirectory = 'tests/Unit'): string
    {
        return "{$testDirectory}/{$className}Test.php";
    }

    /**
     * Generate files in TDD order (tests first, then implementation).
     *
     * @param  array<string, mixed>  $options
     * @return array<string> List of generated file paths
     */
    protected function generateWithTdd(array $options): array
    {
        $generatedFiles = [];

        // Generate tests first
        $testFiles = $this->generateTests($options);
        $generatedFiles = array_merge($generatedFiles, $testFiles);

        // Generate implementation stubs
        $implementationFiles = $this->generateImplementation($options);
        $generatedFiles = array_merge($generatedFiles, $implementationFiles);

        return $generatedFiles;
    }

    /**
     * Generate test files.
     *
     * Override this method to customize test generation.
     *
     * @param  array<string, mixed>  $options
     * @return array<string> List of generated test file paths
     */
    protected function generateTests(array $options): array
    {
        return [];
    }

    /**
     * Generate implementation files.
     *
     * Override this method to customize implementation generation.
     *
     * @param  array<string, mixed>  $options
     * @return array<string> List of generated implementation file paths
     */
    protected function generateImplementation(array $options): array
    {
        return [];
    }
}
