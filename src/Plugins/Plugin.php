<?php

declare(strict_types=1);

namespace LaraForge\Plugins;

use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Contracts\PluginInterface;

abstract class Plugin implements PluginInterface
{
    public function register(LaraForgeInterface $laraforge): void
    {
        // Override in concrete plugins
    }

    public function boot(LaraForgeInterface $laraforge): void
    {
        // Override in concrete plugins
    }

    public function commands(): array
    {
        return [];
    }

    public function generators(): array
    {
        return [];
    }

    public function templatesPath(): ?string
    {
        return null;
    }

    public function stubsPath(): ?string
    {
        return null;
    }
}
