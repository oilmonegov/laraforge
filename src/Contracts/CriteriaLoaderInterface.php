<?php

declare(strict_types=1);

namespace LaraForge\Contracts;

use LaraForge\Criteria\AcceptanceCriteria;
use LaraForge\Criteria\ValidationResult;

/**
 * Interface for loading acceptance criteria from files.
 */
interface CriteriaLoaderInterface
{
    /**
     * Load acceptance criteria from a file.
     *
     * @throws \LaraForge\Exceptions\ConfigurationException
     */
    public function load(string $path): AcceptanceCriteria;

    /**
     * Check if a criteria file exists.
     */
    public function exists(string $path): bool;

    /**
     * Find criteria file for a feature in the criteria directory.
     */
    public function find(string $feature, string $directory): ?string;

    /**
     * Validate that tests cover all criteria by parsing test files.
     *
     * @param  string  $testPath  Path to test file or directory
     */
    public function validate(AcceptanceCriteria $criteria, string $testPath): ValidationResult;

    /**
     * Get the default criteria directory.
     */
    public function defaultDirectory(): string;
}
