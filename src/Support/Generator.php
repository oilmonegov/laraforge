<?php

declare(strict_types=1);

namespace LaraForge\Support;

use LaraForge\Contracts\GeneratorInterface;
use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Exceptions\ValidationException;
use Symfony\Component\Filesystem\Filesystem;

abstract class Generator implements GeneratorInterface
{
    protected Filesystem $filesystem;

    public function __construct(
        protected readonly LaraForgeInterface $laraforge,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function options(): array
    {
        return [];
    }

    public function validate(array $options): void
    {
        $errors = [];

        foreach ($this->options() as $name => $config) {
            if ($config['required'] && !isset($options[$name])) {
                $errors[$name] = ["{$name} is required"];
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Render a stub file with the given variables.
     *
     * @param string $stub The stub filename (without extension)
     * @param array<string, mixed> $variables
     */
    protected function renderStub(string $stub, array $variables = []): string
    {
        $stubPath = "stubs/{$stub}.stub";

        return $this->laraforge->templates()->renderFile($stubPath, $variables);
    }

    /**
     * Write content to a file.
     *
     * @param string $relativePath Path relative to working directory
     * @param string $content Content to write
     */
    protected function writeFile(string $relativePath, string $content): string
    {
        $fullPath = $this->laraforge->workingDirectory() . '/' . ltrim($relativePath, '/');
        $directory = dirname($fullPath);

        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory);
        }

        $this->filesystem->dumpFile($fullPath, $content);

        return $fullPath;
    }

    /**
     * Convert a name to StudlyCase.
     */
    protected function studly(string $value): string
    {
        $words = explode(' ', str_replace(['-', '_'], ' ', $value));
        $studly = implode('', array_map(ucfirst(...), $words));

        return $studly;
    }

    /**
     * Convert a name to camelCase.
     */
    protected function camel(string $value): string
    {
        return lcfirst($this->studly($value));
    }

    /**
     * Convert a name to snake_case.
     */
    protected function snake(string $value): string
    {
        $pattern = '/(?<!^)[A-Z]/';
        $snake = preg_replace($pattern, '_$0', $value);

        return strtolower($snake);
    }

    /**
     * Convert a name to kebab-case.
     */
    protected function kebab(string $value): string
    {
        return str_replace('_', '-', $this->snake($value));
    }
}
