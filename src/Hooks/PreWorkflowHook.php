<?php

declare(strict_types=1);

namespace LaraForge\Hooks;

abstract class PreWorkflowHook extends Hook
{
    public function type(): string
    {
        return 'pre-workflow';
    }
}
