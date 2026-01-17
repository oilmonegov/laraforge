<?php

declare(strict_types=1);

namespace LaraForge\Templates;

use LaraForge\Contracts\TemplateEngineInterface;
use LaraForge\Exceptions\TemplateException;

final class TemplateEngine implements TemplateEngineInterface
{
    /** @var array<int, array{path: string, priority: int}> */
    private array $paths = [];

    /** @var array<string, string> */
    private array $resolvedCache = [];

    public function render(string $template, array $variables = []): string
    {
        // Replace {{ variable }} syntax (including @index, @first, @last in loops)
        $content = preg_replace_callback(
            '/\{\{\s*(@?\w+)\s*\}\}/',
            function (array $matches) use ($variables): string {
                $key = $matches[1];

                return (string) ($variables[$key] ?? $matches[0]);
            },
            $template
        );

        // Replace {!! variable !!} syntax (no escaping placeholder for future use)
        $content = preg_replace_callback(
            '/\{!!\s*(\w+)\s*!!\}/',
            function (array $matches) use ($variables): string {
                $key = $matches[1];

                return (string) ($variables[$key] ?? $matches[0]);
            },
            $content
        );

        // Handle conditional blocks: {{#if variable}}...{{/if}}
        $content = $this->processConditionals($content, $variables);

        // Handle loops: {{#each items}}...{{/each}}
        $content = $this->processLoops($content, $variables);

        return $content;
    }

    public function renderFile(string $path, array $variables = []): string
    {
        $resolvedPath = $this->resolve($path);

        if ($resolvedPath === null) {
            throw new TemplateException("Template not found: {$path}");
        }

        $content = file_get_contents($resolvedPath);
        if ($content === false) {
            throw new TemplateException("Failed to read template: {$resolvedPath}");
        }

        return $this->render($content, $variables);
    }

    public function exists(string $path): bool
    {
        return $this->resolve($path) !== null;
    }

    public function resolve(string $relativePath): ?string
    {
        // Check cache
        if (isset($this->resolvedCache[$relativePath])) {
            return $this->resolvedCache[$relativePath];
        }

        // Sort paths by priority (descending)
        $sortedPaths = $this->paths;
        usort($sortedPaths, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        // Search in order of priority
        foreach ($sortedPaths as $pathInfo) {
            $fullPath = rtrim($pathInfo['path'], '/').'/'.ltrim($relativePath, '/');
            if (file_exists($fullPath)) {
                $this->resolvedCache[$relativePath] = $fullPath;

                return $fullPath;
            }
        }

        return null;
    }

    public function addPath(string $path, int $priority = 0): void
    {
        $this->paths[] = [
            'path' => $path,
            'priority' => $priority,
        ];

        // Clear cache when paths change
        $this->resolvedCache = [];
    }

    private function processConditionals(string $content, array $variables): string
    {
        // {{#if variable}}...{{/if}}
        $pattern = '/\{\{#if\s+(\w+)\s*\}\}(.*?)\{\{\/if\}\}/s';

        return preg_replace_callback(
            $pattern,
            function (array $matches) use ($variables): string {
                $key = $matches[1];
                $innerContent = $matches[2];

                $value = $variables[$key] ?? null;

                // Check truthiness
                if ($this->isTruthy($value)) {
                    return $innerContent;
                }

                return '';
            },
            $content
        );
    }

    private function processLoops(string $content, array $variables): string
    {
        // {{#each items}}...{{/each}}
        $pattern = '/\{\{#each\s+(\w+)\s*\}\}(.*?)\{\{\/each\}\}/s';

        return preg_replace_callback(
            $pattern,
            function (array $matches) use ($variables): string {
                $key = $matches[1];
                $innerTemplate = $matches[2];

                $items = $variables[$key] ?? [];

                if (! is_array($items)) {
                    return '';
                }

                $result = '';
                foreach ($items as $index => $item) {
                    $itemVariables = is_array($item) ? $item : ['this' => $item];
                    $itemVariables['@index'] = $index;
                    $itemVariables['@first'] = $index === 0;
                    $itemVariables['@last'] = $index === count($items) - 1;

                    $result .= $this->render($innerTemplate, array_merge($variables, $itemVariables));
                }

                return $result;
            },
            $content
        );
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value === null || $value === false || $value === '' || $value === 0) {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * Get all registered paths sorted by priority.
     *
     * @return array<int, array{path: string, priority: int}>
     */
    public function paths(): array
    {
        $sortedPaths = $this->paths;
        usort($sortedPaths, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $sortedPaths;
    }
}
