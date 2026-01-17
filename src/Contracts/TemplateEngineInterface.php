<?php

declare(strict_types=1);

namespace LaraForge\Contracts;

/**
 * Interface for template engines.
 *
 * Template engines handle variable substitution and file rendering.
 */
interface TemplateEngineInterface
{
    /**
     * Render a template with the given variables.
     *
     * @param  string  $template  The template content or path
     * @param  array<string, mixed>  $variables  Variables to substitute
     * @return string The rendered content
     */
    public function render(string $template, array $variables = []): string;

    /**
     * Render a template file with the given variables.
     *
     * @param  string  $path  Path to the template file
     * @param  array<string, mixed>  $variables  Variables to substitute
     * @return string The rendered content
     */
    public function renderFile(string $path, array $variables = []): string;

    /**
     * Check if a template exists.
     */
    public function exists(string $path): bool;

    /**
     * Get the resolved path for a template (considering overrides).
     */
    public function resolve(string $relativePath): ?string;

    /**
     * Add a path to search for templates.
     *
     * @param  string  $path  The path to add
     * @param  int  $priority  Higher priority paths are checked first
     */
    public function addPath(string $path, int $priority = 0): void;
}
