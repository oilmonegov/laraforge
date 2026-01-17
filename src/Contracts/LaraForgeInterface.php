<?php

declare(strict_types=1);

namespace LaraForge\Contracts;

/**
 * Main interface for the LaraForge application.
 */
interface LaraForgeInterface
{
    /**
     * Get the LaraForge version.
     */
    public function version(): string;

    /**
     * Get the current working directory.
     */
    public function workingDirectory(): string;

    /**
     * Set the working directory.
     */
    public function setWorkingDirectory(string $path): void;

    /**
     * Get the configuration loader.
     */
    public function config(): ConfigLoaderInterface;

    /**
     * Get the template engine.
     */
    public function templates(): TemplateEngineInterface;

    /**
     * Register an adapter.
     */
    public function registerAdapter(AdapterInterface $adapter): void;

    /**
     * Get the active adapter for the current project.
     */
    public function adapter(): ?AdapterInterface;

    /**
     * Get all registered adapters.
     *
     * @return array<string, AdapterInterface>
     */
    public function adapters(): array;

    /**
     * Register a plugin.
     */
    public function registerPlugin(PluginInterface $plugin): void;

    /**
     * Get all registered plugins.
     *
     * @return array<string, PluginInterface>
     */
    public function plugins(): array;

    /**
     * Register a generator.
     */
    public function registerGenerator(string $name, GeneratorInterface $generator): void;

    /**
     * Get a generator by name.
     */
    public function generator(string $name): ?GeneratorInterface;

    /**
     * Get all registered generators.
     *
     * @return array<string, GeneratorInterface>
     */
    public function generators(): array;

    /**
     * Get the path to the .laraforge override directory.
     */
    public function overridePath(): string;

    /**
     * Check if project-level overrides exist.
     */
    public function hasOverrides(): bool;
}
