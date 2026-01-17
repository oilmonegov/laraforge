<?php

declare(strict_types=1);

namespace LaraForge\Adapters;

use LaraForge\Contracts\AdapterInterface;

abstract class Adapter implements AdapterInterface
{
    protected string $projectPath = '';

    public function priority(): int
    {
        return 10;
    }

    public function commands(): array
    {
        return [];
    }

    public function configuration(): array
    {
        return [];
    }

    public function generators(): array
    {
        return [];
    }

    public function bootstrap(string $projectPath): void
    {
        $this->projectPath = $projectPath;
    }
}
