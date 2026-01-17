<?php

declare(strict_types=1);

namespace LaraForge\Contracts;

/**
 * Interface for configuration loaders.
 */
interface ConfigLoaderInterface
{
    /**
     * Load configuration from a file or directory.
     *
     * @return array<string, mixed>
     */
    public function load(string $path): array;

    /**
     * Get a configuration value.
     *
     * @param  string  $key  Dot-notation key (e.g., 'project.name')
     * @param  mixed  $default  Default value if key doesn't exist
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a configuration value.
     *
     * @param  string  $key  Dot-notation key
     * @param  mixed  $value  Value to set
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool;

    /**
     * Get all configuration.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Merge configuration with existing values.
     *
     * @param  array<string, mixed>  $config
     */
    public function merge(array $config): void;
}
