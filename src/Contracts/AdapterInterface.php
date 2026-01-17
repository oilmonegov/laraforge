<?php

declare(strict_types=1);

namespace LaraForge\Contracts;

/**
 * Interface for framework adapters.
 * 
 * Adapters allow LaraForge to integrate with different frameworks
 * (Laravel, Symfony, Slim, vanilla PHP, etc.)
 */
interface AdapterInterface
{
    /**
     * Get the adapter's unique identifier.
     */
    public function identifier(): string;

    /**
     * Get the adapter's display name.
     */
    public function name(): string;

    /**
     * Get the adapter's version.
     */
    public function version(): string;

    /**
     * Check if this adapter is applicable to the current project.
     */
    public function isApplicable(string $projectPath): bool;

    /**
     * Get the adapter's priority (higher = checked first).
     */
    public function priority(): int;

    /**
     * Get the base path for this adapter's templates.
     */
    public function templatesPath(): string;

    /**
     * Get the base path for this adapter's stubs.
     */
    public function stubsPath(): string;

    /**
     * Get adapter-specific commands.
     *
     * @return array<class-string<\Symfony\Component\Console\Command\Command>>
     */
    public function commands(): array;

    /**
     * Get adapter-specific configuration.
     *
     * @return array<string, mixed>
     */
    public function configuration(): array;

    /**
     * Bootstrap the adapter for the given project.
     */
    public function bootstrap(string $projectPath): void;

    /**
     * Get the generators provided by this adapter.
     *
     * @return array<string, class-string<GeneratorInterface>>
     */
    public function generators(): array;
}
