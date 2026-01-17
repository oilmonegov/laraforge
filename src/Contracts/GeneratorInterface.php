<?php

declare(strict_types=1);

namespace LaraForge\Contracts;

/**
 * Interface for file generators.
 *
 * Generators create files from templates/stubs with variable substitution.
 */
interface GeneratorInterface
{
    /**
     * Get the generator's identifier.
     */
    public function identifier(): string;

    /**
     * Get the generator's display name.
     */
    public function name(): string;

    /**
     * Get the generator's description.
     */
    public function description(): string;

    /**
     * Generate files based on the provided options.
     *
     * @param  array<string, mixed>  $options
     * @return array<string> List of generated file paths
     */
    public function generate(array $options): array;

    /**
     * Get the required options for this generator.
     *
     * @return array<string, array{type: string, description: string, required: bool, default?: mixed}>
     */
    public function options(): array;

    /**
     * Validate the provided options.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws \LaraForge\Exceptions\ValidationException
     */
    public function validate(array $options): void;

    /**
     * Check if this generator supports TDD mode.
     */
    public function supportsTdd(): bool;
}
