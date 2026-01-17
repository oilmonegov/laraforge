<?php

declare(strict_types=1);

namespace LaraForge\Skills\Contracts;

interface ValidationResultInterface
{
    /**
     * Check if validation passed.
     */
    public function isValid(): bool;

    /**
     * Get validation errors.
     *
     * @return array<string, array<string>>
     */
    public function errors(): array;

    /**
     * Get validation warnings (non-blocking issues).
     *
     * @return array<string, array<string>>
     */
    public function warnings(): array;

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool;

    /**
     * Get all error messages as a flat array.
     *
     * @return array<string>
     */
    public function allErrors(): array;
}
