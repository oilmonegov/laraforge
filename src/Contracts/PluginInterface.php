<?php

declare(strict_types=1);

namespace LaraForge\Contracts;

/**
 * Interface for LaraForge plugins.
 * 
 * Plugins can extend LaraForge with additional generators, commands,
 * templates, and functionality.
 */
interface PluginInterface
{
    /**
     * Get the plugin's unique identifier.
     */
    public function identifier(): string;

    /**
     * Get the plugin's display name.
     */
    public function name(): string;

    /**
     * Get the plugin's version.
     */
    public function version(): string;

    /**
     * Get the plugin's description.
     */
    public function description(): string;

    /**
     * Register the plugin with LaraForge.
     */
    public function register(LaraForgeInterface $laraforge): void;

    /**
     * Boot the plugin after all plugins are registered.
     */
    public function boot(LaraForgeInterface $laraforge): void;

    /**
     * Get the commands provided by this plugin.
     *
     * @return array<class-string<\Symfony\Component\Console\Command\Command>>
     */
    public function commands(): array;

    /**
     * Get the generators provided by this plugin.
     *
     * @return array<string, class-string<GeneratorInterface>>
     */
    public function generators(): array;

    /**
     * Get the templates path for this plugin.
     */
    public function templatesPath(): ?string;

    /**
     * Get the stubs path for this plugin.
     */
    public function stubsPath(): ?string;
}
