<?php

declare(strict_types=1);

namespace LaraForge\Support;

use LaraForge\Criteria\AcceptanceCriteria;
use LaraForge\Criteria\AcceptanceCriterion;

/**
 * Base class for test file generation.
 */
abstract class TestGenerator extends Generator
{
    /**
     * Generate a Pest test file from acceptance criteria.
     *
     * @param  array<string, mixed>  $variables  Additional template variables
     */
    protected function generatePestTest(
        AcceptanceCriteria $criteria,
        string $testPath,
        array $variables = [],
    ): string {
        $testCases = $this->buildTestCases($criteria);

        $content = $this->renderStub('pest-test', array_merge([
            'feature' => $criteria->feature,
            'testCases' => $testCases,
        ], $variables));

        return $this->writeFile($testPath, $content);
    }

    /**
     * Generate an empty Pest test file with placeholders.
     *
     * @param  array<string, mixed>  $variables  Additional template variables
     */
    protected function generateEmptyPestTest(
        string $featureName,
        string $testPath,
        array $variables = [],
    ): string {
        $content = $this->renderStub('pest-test', array_merge([
            'feature' => $featureName,
            'testCases' => [],
        ], $variables));

        return $this->writeFile($testPath, $content);
    }

    /**
     * Build test case data from acceptance criteria.
     *
     * @return array<array{id: string, label: string, assertions: array<string>}>
     */
    protected function buildTestCases(AcceptanceCriteria $criteria): array
    {
        $testCases = [];

        foreach ($criteria->all() as $criterion) {
            $testCases[] = $this->buildTestCase($criterion);
        }

        return $testCases;
    }

    /**
     * Build a single test case from a criterion.
     *
     * @return array{id: string, label: string, assertions: array<string>}
     */
    protected function buildTestCase(AcceptanceCriterion $criterion): array
    {
        return [
            'id' => $criterion->id,
            'label' => $criterion->toTestLabel(),
            'assertions' => $criterion->assertions,
        ];
    }

    /**
     * Get the test directory based on test type.
     */
    protected function getTestDirectory(string $type = 'unit'): string
    {
        return match (strtolower($type)) {
            'feature', 'integration' => 'tests/Feature',
            default => 'tests/Unit',
        };
    }

    /**
     * Generate a test file name from a class name.
     */
    protected function getTestFileName(string $className): string
    {
        return "{$className}Test.php";
    }
}
