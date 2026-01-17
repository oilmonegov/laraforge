<?php

declare(strict_types=1);

namespace LaraForge\Hooks;

abstract class ValidationHook extends Hook
{
    public function type(): string
    {
        return 'validation';
    }

    public function isSkippable(): bool
    {
        return false; // Validation hooks should not be skippable by default
    }
}
